<?php

namespace App\Http\Controllers\V1;


use App\Exceptions\UnprocessableEntityHttpException;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\TokenService;
use Qiniu\Auth;
use Qiniu\Config;
use Qiniu\Storage\FormUploader;
use Qiniu\Storage\UploadManager;

/**
 * Class TestController
 * @package App\Http\Controllers\V1
 */
class TestController extends Controller
{

    protected $tokenService;
    protected $request;
    protected $XS;
    protected $XSIndex;
    protected $XSDocument;
    protected $XSSearch;
    protected $params;

    public function __construct(TokenService $tokenService, Request $request)
    {

        parent::__construct();

        $this->tokenService = $tokenService;
        $this->request = $request;

        //接受到的参数
        $this->params = $this->request->all();

    }

    //添加文档到索引
    public function addIndex()
    {

//        //导入数据索引
//        php ./Indexer.php --rebuild --source=mysql://vpgame:vpgame_hangzhouweipei2015@192.168.1.8/vpgame --sql="SELECT * FROM article" --project=article4
//
//        //强制停止重建
//        php ./Indexer.php --stop-rebuild article4
//
//        //查看索引状态
//        php ./Indexer.php --info -p  article4
//
//        //强制刷新 demo 项目的搜索日志
//        php ./Indexer.php --flush-log --project article4
//
//        //清空 demo 项目的索引数据
//        php ./Indexer.php --clean article4


        $value = $this->params;
        // if (!$value['id'] || !$value['author'] || !$value['title'] || !$value['content'] || !$value['post_time']) {
//            throw new UnprocessableEntityHttpException(850005);
        // }

        try {
            $xs = new \XS('article4');

            //初始化索引
            $index = $xs->index;

            // 创建文档对象
            $doc = new \XSDocument();

            $value['content'] = strip_tags($value['content']);
            $value['content'] = lose_space($value['content']);
            $data = array(
                'id' => $value['id'], // 此字段为主键，必须指定
                'author' => $value['author'], // 此字段为主键，必须指定
                'title' => $value['title'], // 此字段为主键，必须指定
                'content' => $value['content'], // 此字段为主键，必须指定
                'post_time' => $value['post_time'], // 此字段为主键，必须指定
                //'chrono' => time()
            );


            $doc->setFields($data);

            //添加到索引
            $tag = $index->add($doc);

            //刷新索引缓存
            $index->flushIndex();
            sleep(2);

            //刷新搜索日志
            $index->flushLogging();
            sleep(2);
            return response_success(['msg' => $tag]);

        } catch (\XSException $e) {
            echo $e;               // 直接输出异常描述
            if (defined('DEBUG'))  // 如果是 DEBUG 模式，则输出堆栈情况
            {
                echo "\n" . $e->getTraceAsString() . "\n";
            }
        }

    }

    public function xs()
    {

        try {
            $key = $this->params['key'] ?? '';
            if (!$key) {
                throw new UnprocessableEntityHttpException(850005);
            }

            $search_begin = microtime(true); //开始执行搜索时间

            $xs = new \XS('article4');


            if (substr_count($key, ' ')) {
                $logKey = $key;
                $key = str_replace(' ', 'AND', $key);
                var_dump('-------连词-------', $key);
            } else {
                //分词 setIgnore过滤标点 setMulti分词长短 getResult获取分词结果
                $tokenizer = new \XSTokenizerScws();
                $key = $tokenizer->setIgnore(true)->setMulti(5)->getResult($key);
                $key = array_pluck($key, 'word');
                $logKey = implode(' ', $key);
                $key = implode('OR', $key);
                var_dump('-------分词-------', $key);
            }

            $search = $xs->search;

            $search->setFuzzy(true); //开启模糊搜索
            //$search->setScwsMulti(8);//搜索语句的分词等级[与setFuzzy使用相互排斥]

            //排序 表示先以 chrono 正序、再以 pid 逆序(pid 是字符串并不是数值所以 12 会排在 3 之后)
            //$sorts = array('chrono' => true, 'pid' => false);
            //$search->setMultiSort($sorts);

            //经纬度排序 lon 代表经度、lat 代表纬度 必须将经度定义在前纬度在后
            //$geo = array('lon' => 116.45, 'lat' => '39.96');
            //$search->setGeodistSort($geo);

            $words = $search->getHotQuery(50, 'total'); //热门词
            var_dump('-------热门词-------', $words);

            $words = $search->getRelatedQuery($key, 10);//相关搜索
            var_dump('-------相关搜索-------', $words);

            $docs = $search->getExpandedQuery($key); //搜索建议
            var_dump('--------搜索建议------', $docs);


            $docs = $search->terms($key); //高亮搜索词
            var_dump('--------高亮搜索词------', $docs);

            //$search->addWeight('title', $this->params['key']); //增加关键字权重

            $count = $search->count($key);
            var_dump('-------搜索匹配总数-------', $count);

            $docs = $search->search($key); //执行搜索
            $log = $search->getQuery($key); //搜索语句
            var_dump('-------sql-------', $log);

            $search_cost = microtime(true) - $search_begin; //执行结束时间
            var_dump('-------执行时间-------', $search_cost);

            $arr = [];
            if (false === empty($docs)) {
                foreach ($docs as $key => $value) {
                    $arr[] = $value->getFieldsArray();
                }
            }
            var_dump('-------结果-------', $arr);

            //添加搜索记录到缓存去
            $search->addSearchLog($logKey);
            //刷新搜索日志
            $xs->index->flushLogging();

        } catch (\XSException $e) {
            echo $e;               // 直接输出异常描述
            if (defined('DEBUG'))  // 如果是 DEBUG 模式，则输出堆栈情况
            {
                echo "\n" . $e->getTraceAsString() . "\n";
            }
        }

    }

    public function qiniu()
    {
        //QINIU_ACCESS_KEY=r4Puq7v6E1MrD8q2SZpRrtX3_exPMOHPuVRgquAT
        //QINIU_SECRET_KEY=iqMsNBgQ5_nLkdpSNwjxieDLvno8YMcH1PXHTYGz
        //QINIU_BUCKET=weiba-ms
        //QINIU_DOMAIN=http://7xk9pc.com2.z0.glb.qiniucdn.com/

        $accessKey = 'jVIkLNl8FzaeCK8H5AxPLYi49qlmc86572ITnbiM';
        $secretKey = 'A1JOHdGbg0IoxcoZYmoHtjfzbgwp51EDfzusMNkm';
        $qiniuUrl = 'http://ozg3kv9uz.bkt.clouddn.com/';
        $testAuth = new Auth($accessKey, $secretKey);

        $name = '1123.jpg';
        $fileName = base_path() . '/public/1.jpg';
        $bucketName = 'quwan';
        $token = $testAuth->uploadToken($bucketName, $name);

        $upManager = new UploadManager();
        $res = $upManager->putFile($token, $name, $fileName);
        if (true === empty($res[0]['key'])) {
            throw new UnprocessableEntityHttpException(850006);
        }

        return response_success(['url' => $qiniuUrl, 'file_name' => $res[0]['key']]);
    }

    public function sendSms()
    {

//        用法
//
//        use Wenpeng\Qsms\Client;
//        $client = new Client($appID, $appKey);
//        单发短信
//
//        use Wenpeng\Qsms\Request\Single;
//        $sms = new Signle($client, 0);
//        单发普通短信
//        use Wenpeng\Qsms\Request\Single;
//        $sms = new Signle($client, 0);
//        $sms->target('18800001111', '86');
//        $response = $sms->normal('这是测试短信内容');
//        {
//        "result": "0", //0表示成功(计费依据)，非0表示失败
//        "errmsg": "", //result非0时的具体错误信息
//        "ext": "some msg", //用户的session内容，腾讯server回包中会原样返回
//        "sid": "xxxxxxx", //标识本次发送id
//        "fee": 1 //短信计费的条数
//        }

//        单发模板短信
//        $sms->target('18800001111', '86');
//        // 短信正文模板编号 1000, 短信正文参数 ['123456', 30]
//        $response = $sms->template(1000, ['123456', 30]);
//        {
//        "result": "0", //0表示成功(计费依据)，非0表示失败
//        "errmsg": "", //result非0时的具体错误信息
//        "ext": "some msg", //用户的session内容，腾讯server回包中会原样返回
//        "sid": "xxxxxxx", //标识本次发送id
//        "fee": 1 //短信计费的条数
//        }

//        return response_success(['token' => $token]);
    }

    public function login()
    {
        //生成 token
        $userId = 12345;
        $token = $this->tokenService->createToken($userId, 'web');

        return response_success(['token' => $token]);
    }


    public function user()
    {
        return response_success(['userId' => $this->userId]);
    }

    public function logout()
    {
        $bearerToken = $this->request->server->getHeaders()['AUTHORIZATION'] ?? '';
        $claims = $this->tokenService->getJwtClaims($bearerToken);
        $this->tokenService->revokeToken($claims['jti']);

        return response_success(['msg' => '退出成功']);
    }

}
