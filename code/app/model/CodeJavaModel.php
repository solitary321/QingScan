<?php


namespace app\model;


use think\facade\Db;

class CodeJavaModel extends BaseModel
{
    public static function code_java()
    {
        $codePath = "/data/codeCheck";
        while (true) {
            processSleep(1);
            ini_set('max_execution_time', 0);
            $list = Db::name('code')->whereTime('java_scan_time', '<=', date('Y-m-d H:i:s', time() - (86400 * 15)))
                ->where('is_delete', 0)->limit(1)->orderRand()->select()->toArray();
            foreach ($list as $k => $v) {
                PluginModel::addScanLog($v['id'], __METHOD__, 0, 2);
                self::scanTime('code', $v['id'], 'java_scan_time');

                $value = $v;
                $prName = cleanString($value['name']);
                $codeUrl = $value['ssh_url'];
                $filepath = "{$codePath}/{$prName}";
                if (!file_exists($filepath)) {
                    downCode($codePath, $prName, $codeUrl, $value['is_private'], $value['username'], $value['password'], $value['private_key']);
                }
                $fileArr = getFilePath($filepath, 'pom.xml');
                if (!$fileArr) {
                    PluginModel::addScanLog($v['id'], __METHOD__, 2,2);
                    addlog("JAVA依赖扫描失败,未找到依赖文件:{$filepath}");
                    continue;
                }
                foreach ($fileArr as $val) {
                    $result = xmlToArray($val['file']);
                    if ($result) {
                        $data = [
                            'user_id' => $v['user_id'],
                            'code_id' => $v['id'],
                            'modelVersion' => $result['modelVersion'],
                            'groupId' => isset($result['groupId']) ? $result['groupId'] : '',
                            'artifactId' => isset($result['artifactId']) ? $result['artifactId'] : '',
                            'version' => isset($result['version']) ? $result['version'] : '',
                            'modules' => isset($result['modules']) ? json_encode($result['modules']) : '',
                            'packaging' => isset($result['packaging']) ? $result['packaging'] : '',
                            'name' => isset($result['name']) ? $result['name'] : '',
                            'comment' => isset($result['comment']) ? json_encode($result['comment']) : '',
                            'url' => isset($result['url']) ? $result['url'] : '',
                            'properties' => isset($result['properties']) ? json_encode($result['properties']) : '',
                            'dependencies' => isset($result['dependencies']) ? json_encode($result['dependencies']) : '',
                            'build' => isset($result['build']) ? json_encode($result['build']) : '',
                            'create_time' => date('Y-m-d H:i:s', time()),
                        ];
                        Db::name('code_java')->insert($data);
                        addlog("JAVA依赖扫描数据写入成功,内容为:" . json_encode($data));
                    } else {
                        addlog("JAVA依赖扫描失败,项目文件内容为空:{$val['file']}");
                    }
                }
                PluginModel::addScanLog($v['id'], __METHOD__, 1,2);
            }
            sleep(10);
        }
    }
}