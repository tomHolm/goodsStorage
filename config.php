<?php

class Config
{
    const DB_HOST = 'localhost';
    const DB_USER = 'goodsuser';
    const DB_PW = 'Qwerty123';
    const DB_SCHEMA = 'vktest';

    const MCACHED_HOST = 'localhost';
    const MCACHED_PORT = 11211;

    const PRIMARY_LIST_KEY = 'pList';
    const PRIMARY_LIST_COUNT_KEY = 'listCount';
    const ITEMS_COUNT_KEY = 'totalCount';
    const PRIMARY_ID_PART_SIZE = 40000;

    const PAGE_SIZE = 20;

    const GOODS_IMG_PATH = 'images/';
    const GOODS_IMG_WIDTH = 32;
    const GOODS_IMG_HEIGHT = 32;
}