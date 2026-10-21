<?php
const DB_HOST = '127.0.0.1', DB_NAME = 'rcm', DB_USER = 'root', DB_PASS = '';
const APP_TZ = 'Asia/Jakarta', DB_TZ_OFFSET = '+07:00';
const PHP_CLI = 'php';            // path php CLI untuk scan di background
const ONLINE_SECONDS = 60;        // offline jika heartbeat lebih lama dari ini
const HEARTBEAT_INTERVAL = 30;
const WOL_BROADCAST = '192.168.18.255'; // alamat broadcast subnet lab
