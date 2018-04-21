<?php
header('Content-type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
require_once 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$currentPage = !empty($_GET['page']) ? $_GET['page'] : 1;
$command = !empty($_GET['command']) ? $_GET['command'] : '';
$count = !empty($_GET['count']) ? $_GET['count'] : 1;

$mcache = new Memcache();
$mcache->addServer(Config::MCACHED_HOST, Config::MCACHED_PORT);

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

// Инициализация данных, разбиение списка первичных ключей на части

function updateCache(Memcache $mcache)
{
    $res = [];
    $goodsCount = getGoodsCount();
    $mcache->set(Config::ITEMS_COUNT_KEY, $goodsCount);
    $parts = ceil($goodsCount/Config::PRIMARY_ID_PART_SIZE);
    $mcache->set(Config::PRIMARY_LIST_COUNT_KEY, 0);
    for ($i = $parts; $i > 0; $i--) {
        $pIds = [];
        $cnt = $mcache->get(Config::PRIMARY_LIST_COUNT_KEY);
        $offset = $cnt * Config::PRIMARY_ID_PART_SIZE;
        $cursor = getConnection()->query("select id from goods order by id desc limit $offset, " . Config::PRIMARY_ID_PART_SIZE);
        if ($cursor !== false) {
            while ($row = $cursor->fetch_assoc()) {
                $pIds[] = $row['id'];
            }
            $key = Config::PRIMARY_LIST_KEY.$cnt;
            $res[$key]['startIdx'] = $pIds[0];
            $res[$key]['count'] = count($pIds);
            $mcache->set(Config::PRIMARY_LIST_KEY.$cnt, $pIds, MEMCACHE_COMPRESSED);
            $mcache->increment(Config::PRIMARY_LIST_COUNT_KEY);
        }
    }
    echo json_encode($res);
}

// Получение списка товаров

function getIdPartByPage($page) {
    return (int)floor(($page-1)*Config::PAGE_SIZE/Config::PRIMARY_ID_PART_SIZE);
}

function getGoodsCount()
{
    $mysqli = getConnection();
    $cursor = $mysqli->query('select count(*) as count from goods');
    return (int)$cursor->fetch_assoc()['count'];
}

function getItem($id, Memcache $mcache, &$result) {
    if (($item = $mcache->get('item'.$id)) === false) {
        $cursor = getConnection()->query('select * from goods where id = '.$id);
        if (!empty($cursor)) {
            $item = $cursor->fetch_assoc();
            $mcache->set('item'.$id, $item);
        }
    }
    $result[] = $item;
}

function getGoods($page, Memcache $mcache) {
    $pIds = $mcache->get(Config::PRIMARY_LIST_KEY.getIdPartByPage($page));
    $goods = [];
    if ($pIds !== false) {
        $start = (Config::PAGE_SIZE * ($page - 1)) % Config::PRIMARY_ID_PART_SIZE;
        $end = (Config::PAGE_SIZE * $page) % Config::PRIMARY_ID_PART_SIZE;
        $counter = 0;
        foreach ($pIds as $id) {
            if (count($goods) == Config::PAGE_SIZE) {
                break;
            } elseif ($counter >= $start && $counter < $end) {
                getItem($id, $mcache, $goods);
            }
            $counter++;
        }
    }
    echo json_encode([
        'goods' => $goods,
        'page' => $page,
        'pagesCount' => $pIds === false ? 1 : ceil(((int)$mcache->get(Config::ITEMS_COUNT_KEY))/Config::PAGE_SIZE)
    ]);
}

// Удаление элемента

function removeIdFromList(Memcache $mcache, $part, $removeIdx) {
    $partsCount = $mcache->get(Config::PRIMARY_LIST_COUNT_KEY);
    $newElem = false;
    for ($i = $partsCount-1; $i >= $part; $i--) {
        $pIds = $mcache->get(Config::PRIMARY_LIST_KEY.$i);
        if ($newElem !== false) {
            array_push($pIds, $newElem);
        }
        if ($i != $part) {
            $newElem = array_shift($pIds);
        } else {
            unset($pIds[$removeIdx]);
            $mcache->decrement(Config::ITEMS_COUNT_KEY);
        }
        if (count($pIds) === 0) {
            $mcache->delete(Config::PRIMARY_LIST_KEY.$i);
            $mcache->decrement(Config::PRIMARY_LIST_COUNT_KEY);
        } else {
            $mcache->replace(Config::PRIMARY_LIST_KEY . $i, $pIds);
        }
    }
}

function removeGood(Memcache $mcache) {
    $removePos = mt_rand(0, $mcache->get(Config::ITEMS_COUNT_KEY));
    $removePart = floor($removePos/Config::PRIMARY_ID_PART_SIZE);
    $removePos = $removePos % Config::PRIMARY_ID_PART_SIZE;
    $counter = 0;
    $pIds = $mcache->get(Config::PRIMARY_LIST_KEY.$removePart);
    $removeIdx = false;
    foreach ($pIds as $k => $id) {
        if ($removeIdx !== false) {
            break;
        } elseif ($removePos == $counter) {
            $removeIdx = $k;
            $mysqli = getConnection();
            $mysqli->query('delete from goods where id = ' . $id);
            $mysqli->commit();
            $mcache->delete('item' . $id);
        }
        $counter++;
    }
    if ($mcache->get(Config::PRIMARY_LIST_COUNT_KEY) > 1) {
        removeIdFromList($mcache, $removePart, $removeIdx);
    } else {
        $mcache->replace(Config::PRIMARY_LIST_KEY, $pIds);
    }
}

// Добавление элемента

function getImgName()
{
    $arr = array_filter(scandir(__DIR__.'/images'), function ($item) {return strlen($item) > 3;});
    $pos = mt_rand(0, count($arr)-1);
    $counter = 0;
    foreach ($arr as $k => $v) {
        if ($pos == $counter++) {
            return Config::GOODS_IMG_PATH.$v;
        }
    }
    return '';
}

function addIdToList(Memcache $mcache, $id) {
    $parts = (int)$mcache->get(Config::PRIMARY_LIST_COUNT_KEY);
    $newElem = $id;
    for ($i = 0; $i < $parts; $i++) {
        $pIds = $mcache->get(Config::PRIMARY_LIST_KEY.$i);
        array_unshift($pIds, $newElem);
        if ($i != $parts-1) {
            $newElem = array_pop($pIds);
        } elseif (count($pIds) > Config::PRIMARY_ID_PART_SIZE) {
            $newElem = array_pop($pIds);
            $mcache->set(Config::PRIMARY_LIST_KEY.$parts, [$newElem]);
            $mcache->increment(Config::PRIMARY_LIST_COUNT_KEY);
        }
        $mcache->replace(Config::PRIMARY_LIST_KEY.$i, $pIds);
    }
}

function addGood(Memcache $mcache) {
    $nameRandom = mt_rand(1, 10000);
    $descRandom = mt_rand(1, 10000);
    $priceRandom = mt_rand(1, 10000);
    $imgName = getImgName();

    $item = [
        'name' => "name$nameRandom",
        'description' => "desc$descRandom",
        'price' => round(($priceRandom/3), 2),
        'image_url' => $imgName
    ];

    $mysqli = getConnection();
    $sql = 'insert into goods (`name`, `description`, `price`, `image_url`) values (';
    $sql .= implode(',', ['\''.$item['name'].'\'', '\''.$item['description'].'\'', $item['price'], '\''.$item['image_url'].'\'']);
    $sql .= ')';
    $mysqli->query($sql);
    if (!empty($mysqli->error)) {
        throw new \Exception(sprintf('Error occurred while adding new row: %s - %s', $mysqli->errno, $mysqli->error));
    }
    $item['id'] = $mysqli->insert_id;
    $mysqli->commit();
    $mcache->set('item'.$item['id'], $item);
    $mcache->increment(Config::ITEMS_COUNT_KEY);
    addIdToList($mcache, $item['id']);
}

// Выбор нужной функции

try {
    switch ($command) {
        case 'init':
            updateCache($mcache);
            break;
        case 'flush':
            $mcache->flush();
            break;
        case 'add':
        case 'remove':
            $method = $command . 'Good';
            $method($mcache);
        default:
            getGoods($currentPage, $mcache);
    }
}
catch (\Exception $e) {
    echo json_encode([
        'errorMsg' => $e->getMessage()
    ]);
}