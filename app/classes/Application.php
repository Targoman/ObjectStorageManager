<?php
/**
 * @author: Kambiz Zandi <kambizzandi@gmail.com>
 */

namespace Targoman\ObjectStorageManager\classes;

use \Exception;
use Targoman\Framework\core\Application as BaseApplication;
use Targoman\Framework\helpers\ArrayHelper;

class Application extends BaseApplication {
    public $instanceId;
    public $fetchlimit = 1;

    public function run() {
        if (empty($this->instanceId)) {
            $this->instanceId = "OSM-" . uniqid(true);

            $localParams = [];
            $fileName = __DIR__ . '/../config/params-local.php';
            if (file_exists($fileName)) {
                $localParams = require($fileName);
            }

            $localParams["app"]["instanceId"] = $this->instanceId;

            $conf = ArrayHelper::dump($localParams);
            $conf = "<?php\n" . "return " . $conf . ";\n";
            file_put_contents($fileName, $conf);
        }

        //-------------------------
        $this->logger->setActor($this->instanceId);

        $this->logger->log("---------- Starting Object Storage Manager ----------");

        //-------------------------
        $command = 'ps aux | grep "ObjectStorageManager.php" | grep "php "';
        exec($command, $output, $return_var);
        // var_dump($output);
        // var_dump($return_var);
        if ($return_var != 0)
            throw new Exception("Error in `ps`");
        // $output = shell_exec('ps aux | grep "ObjectStorageManager.php" | grep "php "');
        // if (substr_count($output, "\n") > 2)
        if (count($output) > 2)
            throw new Exception("Object Storage Manager is running");

        //-------------------------
        $db = $this->db;

        $qry = <<<SQL
            SELECT *
              FROM tblUploadQueue
        INNER JOIN tblUploadFiles
                ON tblUploadFiles.uflID = tblUploadQueue.uqu_uflID
        INNER JOIN tblUploadGateways
                ON tblUploadGateways.ugwID = tblUploadQueue.uqu_ugwID
             WHERE (uquLockedAt IS NULL
                OR uquLockedAt < DATE_SUB(NOW(), INTERVAL 1 HOUR)
                OR uquLockedBy = ?
                   )
               AND (uquStatus = 'N'
                OR (uquStatus = 'E'
               AND uquLastTryAt < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                   )
                   )
          ORDER BY uquCreationDateTime ASC
             LIMIT {$this->fetchlimit}
SQL;
        $data = $db->selectAll($qry, [
            1 => $this->instanceId,
        ]);

        if (empty($data)) {
            $this->logger->log("Nothing to do");
            return;
        }

        $this->logger->log("Count of items: " . count($data));

        $ids = array_map(function ($ar) { return $ar["uquID"]; }, $data);
        if (empty($ids))
            throw new Exception("Error in gathering ids");

        $this->logger->log("Items ID: " . implode(',', $ids));

        //lock items
        $qry = strtr(<<<SQL
            UPDATE tblUploadQueue
               SET uquLockedAt = NOW()
                 , uquLockedBy = ?
             WHERE uquID IN (:ids)
SQL
        , [
            ':ids' => implode(',', $ids),
        ]);
        $rowsCount = $db->execute($qry, [
            1 => $this->instanceId,
        ]);

        // print_r([implode(',', $ids), $rowsCount]);

        $sdk = new \Aws\Sdk([
            'version' => 'latest',
            // 'region' => 'us-west-2',
        ]);

        //ugwID => s3Client
        $s3Clients = [];

        foreach ($data as $row) {
            $uquID                      = $row["uquID"];
            $uqu_uflID                  = $row["uqu_uflID"];
            $uqu_ugwID                  = $row["uqu_ugwID"];
            $uquLockedAt                = $row["uquLockedAt"];
            $uquLastTryAt               = $row["uquLastTryAt"];
            $uquStoredAt                = $row["uquStoredAt"];
            $uquResult                  = $row["uquResult"];
            $uquStatus                  = $row["uquStatus"];
            $uquCreationDateTime        = $row["uquCreationDateTime"];
            $uquCreatedBy_usrID         = $row["uquCreatedBy_usrID"];
            $uquUpdatedBy_usrID         = $row["uquUpdatedBy_usrID"];
            $uflID                      = $row["uflID"];
            $uflPath                    = $row["uflPath"];
            $uflOriginalFileName        = $row["uflOriginalFileName"];
            $uflCounter                 = $row["uflCounter"];
            $uflStoredFileName          = $row["uflStoredFileName"];
            $uflSize                    = $row["uflSize"];
            $uflFileType                = $row["uflFileType"];
            $uflMimeType                = $row["uflMimeType"];
            $uflLocalFullFileName       = $row["uflLocalFullFileName"];
            $uflStatus                  = $row["uflStatus"];
            $uflCreationDateTime        = $row["uflCreationDateTime"];
            $uflCreatedBy_usrID         = $row["uflCreatedBy_usrID"];
            $uflUpdatedBy_usrID         = $row["uflUpdatedBy_usrID"];
            $ugwID                      = $row["ugwID"];
            $ugwName                    = $row["ugwName"];
            $ugwType                    = $row["ugwType"];
            $ugwBucket                  = $row["ugwBucket"];
            $ugwEndpointUrl             = $row["ugwEndpointUrl"];
            $ugwEndpointIsVirtualHosted = $row["ugwEndpointIsVirtualHosted"];
            $ugwMetaInfo                = $row["ugwMetaInfo"];
            $ugwAllowedFileTypes        = $row["ugwAllowedFileTypes"];
            $ugwAllowedMimeTypes        = $row["ugwAllowedMimeTypes"];
            $ugwAllowedMinFileSize      = $row["ugwAllowedMinFileSize"];
            $ugwAllowedMaxFileSize      = $row["ugwAllowedMaxFileSize"];
            $ugwMaxFilesCount           = $row["ugwMaxFilesCount"];
            $ugwMaxFilesSize            = $row["ugwMaxFilesSize"];
            $ugwCreatedFilesCount       = $row["ugwCreatedFilesCount"];
            $ugwCreatedFilesSize        = $row["ugwCreatedFilesSize"];
            $ugwDeletedFilesCount       = $row["ugwDeletedFilesCount"];
            $ugwDeletedFilesSize        = $row["ugwDeletedFilesSize"];
            $ugwLastActionTime          = $row["ugwLastActionTime"];
            $ugwStatus                  = $row["ugwStatus"];
            $ugwCreationDateTime        = $row["ugwCreationDateTime"];
            $ugwCreatedBy_usrID         = $row["ugwCreatedBy_usrID"];
            $ugwUpdatedBy_usrID         = $row["ugwUpdatedBy_usrID"];

            $this->logger->log("Storing ($uflLocalFullFileName) id($uquID)");

            $isOk = false;
            $runResult = NULL;
            try {
                if (file_exists($uflLocalFullFileName) == false)
                    throw new Exception("file not found: $uflLocalFullFileName");

                if (empty($ugwMetaInfo) == false)
                    $ugwMetaInfo = json_decode($ugwMetaInfo, true);

                if ($ugwType == '3') { //S3
                    echo "to $ugwEndpointUrl/$ugwBucket/$uflPath/$uflStoredFileName: ";

                    if (isset($s3Clients[$ugwID]))
                        $s3Client = $s3Clients[$ugwID];
                    else {
                        $credentials = new \Aws\Credentials\Credentials($ugwMetaInfo["AccessKey"], $ugwMetaInfo["SecretKey"]);

                        $s3Client = $sdk->createS3([
                            'region' => '',
                            'credentials' => $credentials,
                            'endpoint' => $ugwEndpointUrl,
                            'use_path_style_endpoint' => !$ugwEndpointIsVirtualHosted,
                        ]);

                        $s3Clients[$ugwID] = $s3Client;
                    }

                    // $result = $s3Client->getObjectUrl($ugwBucket, implode('/', [$uflPath, $uflStoredFileName]));
                    // echo '[' . $result . "]\n";

                    $result = $s3Client->putObject([
                        'Bucket' => $ugwBucket,
                        'Key' => implode('/', [$uflPath, $uflStoredFileName]),
                        'Body' => file_get_contents($uflLocalFullFileName),
                    ]);
                    // echo '[' . $result . "]\n";

                    $isOk = true;

                } else if ($ugwType == 'N') { //NFS
                    $Path = $ugwMetaInfo["Path"] . '/' . $uflPath;

                    echo "to $Path/$uflStoredFileName: ";

                    if (file_exists($Path) == false)
                        if (mkdir($Path, 0777, true) == false)
                            throw new Exception("error in create folder");

                    if (copy($uflLocalFullFileName, "$Path/$uflStoredFileName") == false)
                        throw new Exception("error in copy file");

                    $isOk = true;

                } else { //unknown ugwType
                    throw new Exception("unknown gateway type: $ugwType");
                }
            } catch(Exception $_exp) {
                $isOk = false;
                $runResult = $_exp->getMessage();
            }

            if ($isOk)
                $this->logger->log("[OK]");
            else
                $this->logger->log("[FAILED: $runResult]");

            $_uquStoredAt = ($isOk ? 'NOW()' : 'NULL');
            $qry = <<<SQL
            UPDATE tblUploadQueue
               SET uquLockedAt = NULL
                 , uquLockedBy = NULL
                 , uquLastTryAt = NOW()
                 , uquStoredAt = {$_uquStoredAt}
                 , uquResult = ?
                 , uquStatus = ?
             WHERE uquID = ?
SQL;
            $rowsCount = $db->execute($qry, [
                1 => $runResult,
                2 => ($isOk ? 'S' : 'E'),
                3 => $uquID,
            ]);
        }
    }

};
