<?php
header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
require_once 'config.php';

$currentPage = !empty($_GET['page']) ? $_GET['page'] : 1;
$command = !empty($_GET['command']) ? $_GET['command'] : '';
$count = !empty($_GET['count']) ? $_GET['count'] : 1;

$mcache = new Memcached();
$mcache->addServer(Config::MCACHED_HOST, Config::MCACHED_PORT);
// TODO resolve problem with bad connection to memcached

function getConnection() {
    $connect = mysqli_connect(
        Config::DB_HOST,
        Config::DB_USER,
        Config::DB_PW,
        Config::DB_SCHEMA
    );
    if (!$connect) {
        throw new \Exception(sprintf('Error occured while connecting to MySql: %s - %s', mysqli_connect_errno(), mysqli_connect_error()));
    }
    return $connect;
};

function getGoodsCount() {
    $mysqli = getConnection();
    $cursor = $mysqli->query('select count(*) as count from goods');
    $countRow = $cursor->fetch_assoc();
    return (int)$countRow['count'];
}

function getPagesCount() {

    return ceil(getGoodsCount()/Config::PAGE_SIZE);
}

function getGoods($page, Memcached $mcache) {
    $result = $mcache->get('page'.$page);
    if (empty($result)) {
        $mysqli = getConnection();

        $offset = ($page - 1) * Config::PAGE_SIZE;
        $cursor = $mysqli->query('select * from goods g join (select id from goods order by id DESC limit '.$offset.', '.Config::PAGE_SIZE.') gj on gj.id = g.id');

        $goods = [];
        if ($cursor !== false) {
            while ($row = $cursor->fetch_assoc()) {
                $goods[] = $row;
            }
        }
        $result = json_encode([
            'page' => $page,
            'goods' => $goods,
            'pagesCount' => getPagesCount()
        ]);
        $mcache->set('page'.$page, $result, Config::MCACHED_TTL);
    }
    echo $result;
}

function removeGood() {
    $offset = mt_rand(0, getGoodsCount() - 1);
    $mysqli = getConnection();
    $cursor = $mysqli->query("select id from goods limit $offset, 1;");
    $offId = $cursor->fetch_assoc()['id'];
    $mysqli->query("delete from goods where id = $offId");
    if (!empty($mysqli->error)) {
        throw new \Exception(sprintf('Error occurred while removing row: %s - %s', $mysqli->errno, $mysqli->error));
    }
    $mysqli->commit();
}

function addGood() {
    $nameRandom = mt_rand(1, 10000);
    $descRandom = mt_rand(1, 10000);
    $priceRandom = mt_rand(1, 10000);
    $imgName = Config::GOODS_IMG_PATH.implode('-', ['img', $nameRandom, $descRandom, $priceRandom]).'.png';

    if (!file_exists(__DIR__.'/'.$imgName)) {
        $height = Config::GOODS_IMG_HEIGHT;
        $width = Config::GOODS_IMG_WIDTH;
        $img = imagecreate($width, $height);
        imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        imagepng($img, __DIR__.'/'.$imgName);
    }

    $mysqli = getConnection();
    $sql = 'insert into goods (`name`, `description`, `price`, `image_url`) values (';
    $sql .= implode(',', ["'name$nameRandom'", "'desc$descRandom'", round(($priceRandom/3), 2), "'$imgName'"]);
    $sql .= ')';
    $mysqli->query($sql);
    if (!empty($mysqli->error)) {
        throw new \Exception(sprintf('Error occurred while adding new row: %s - %s', $mysqli->errno, $mysqli->error));
    }
    $mysqli->commit();
}
try {
    switch ($command) {
        case 'flush':
            $mcache->flush();
            break;
        case 'add':
        case 'remove':
            $mcache->flush();
            $method = $command . 'Good';
            for ($i = 0; $i < $count; $i++) {
                $method();
            }
        default:
            getGoods($currentPage, $mcache);
    }
}
catch (\Exception $e) {
    echo json_encode([
        'errorMsg' => $e->getMessage()
    ]);
}