<?php
$start = microtime(true);
header('Content-type: application/json; charset=UTF-8');

$pagesSize = 20;
$currentPage = !empty($_GET['page']) ? $_GET['page'] : 1;
$command = !empty($_GET['command']) ? $_GET['command'] : '';
$count = !empty($_GET['count']) ? $_GET['count'] : 1;
const TTL = 600;
// TODO set all params to config

$mcache = new Memcache();
$mcache->addServer('localhost', 11211);

function getConnection() {
    return mysqli_connect(
        get_cfg_var('db.host'),
        get_cfg_var('db.username'),
        get_cfg_var('db.pw'),
        get_cfg_var('db.schema')
    );
};

function getGoodsCount() {
    $mysqli = getConnection();
    $cursor = $mysqli->query('select count(*) as count from goods');
    $countRow = $cursor->fetch_assoc();
    return (int)$countRow['count'];
}

function getPagesCount($size) {

    echo json_encode(['pagesCount' => ceil(getGoodsCount()/$size)]);
}

function getGoods($page, $size, Memcache $mcache) {
    $result = $mcache->get('page'.$page);
    if ($result === false) {
        $mysqli = getConnection();

        $offset = ($page - 1) * $size;
        $cursor = $mysqli->query("select * from vktest.goods g join (select id from vktest.goods order by id DESC limit $offset, $size) gj on gj.id = g.id");

        $goods = [];
        if ($cursor !== false) {
            while ($row = $cursor->fetch_assoc()) {
                $goods[] = $row;
            }
        }
        $result = json_encode([
            'page' => $page,
            'goods' => $goods
        ]);
        $mcache->set('page'.$page, $result, 0, TTL);
    }
    echo $result;
}

function removeGood() {
    $offset = mt_rand(1, getGoodsCount());
    $mysqli = getConnection();
    $cursor = $mysqli->query("select id from vktest.goods limit $offset, 1;");
    $offId = $cursor->fetch_assoc()['id'];
    $mysqli->query("delete from vktest.goods where id = $offId");
    if (!empty($mysqli->error)) {
        throw new \Exception(sprintf('Error occurred while removing row: %s - %s', $mysqli->errno, $mysqli->error));
    }
    $mysqli->commit();
}

function addGood() {
    $nameRandom = mt_rand(1, 10000);
    $descRandom = mt_rand(1, 10000);
    $priceRandom = mt_rand(1, 10000);
    $imgName = 'images/'.implode('-', ['img', $nameRandom, $descRandom, $priceRandom]).'.png';

    if (!file_exists(__DIR__.'/'.$imgName)) {
        $height = 32;
        $width = 32;
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
        case 'getpagescount':
            getPagesCount($pagesSize);
            break;
        case 'add':
        case 'remove':
            $mcache->flush();
            $method = $command . 'Good';
            for ($i = 0; $i < $count; $i++) {
                $method();
            }
        default:
            getGoods($currentPage, $pagesSize, $mcache);
    }
}
catch (\Exception $e) {
    echo json_encode([
        'errorMsg' => $e->getMessage()
    ]);
}