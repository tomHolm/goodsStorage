<?php
header('Content-type: application/json; charset=UTF-8');

$pagesSize = 20;
$page = 1;
$getMysqli = function() {
    return mysqli_connect(
        get_cfg_var('db.host'),
        get_cfg_var('db.username'),
        get_cfg_var('db.pw'),
        get_cfg_var('db.schema')
    );
};
$command = !empty($_GET['command']) ? $_GET['command'] : '';

switch ($command) {
    case 'add':
        $random = rand(3, 1000);
        $sql = 'insert into goods (`name`, `description`, `price`) values '.
            sprintf('(\'%s\', \'%s\', %s)', "good$random", "random desc $random", round(((float)$random) / 3, 2));
        $mysqli = $getMysqli();
        $mysqli->query($sql);
        $mysqli->commit();
        break;
    case 'getcount':
        $mysqli = $getMysqli();
        $cursor = $mysqli->query('select count(*) as count from goods');
        $countRow = $cursor->fetch_assoc();
        echo $countRow['count'];
        break;
    case 'getitems':
        if (!empty($_GET['page'])) {
            $page = $_GET['page'];
        };
    default:
        $mysqli = $getMysqli();
        $cursor = $mysqli->query('select count(*) as `count` from goods');
        $countRow = $cursor->fetch_assoc();

        $pagesCount = ceil(((int)$countRow['count'])/$pagesSize);

        $offset = ($page-1)*$pagesSize;
        $res = $mysqli->query("select * from vktest.goods g join (select id from vktest.goods order by id DESC limit $offset, $pagesSize) gj on gj.id = g.id");
        $goods = [];
        if ($res !== false) {
            while ($row = $res->fetch_assoc()) {
                $goods[] = $row;
            }
        }

        echo json_encode([
            'pagesCount' => $pagesCount,
            'goods' => $goods
        ]);
}
