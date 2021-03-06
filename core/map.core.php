<?php

/**
 * Ultima PHP - OpenSource Ultima Online Server written in PHP
 * Version: 0.1 - Pre Alpha
 */
class Map {
    /**
     * Map loading variables
     */
    public static $maps        = [];
    public static $mapSizes    = [];
    public static $chunks      = [];
    public static $chunkSize   = 512; // Number in square
    public static $tileMatrix  = [];
    private static $serialData = [];
    private static $tiledata   = [];
    private static $lastSerial = [
        'mobile' => 0,
        'object' => 0,
    ];

    public function __construct() {
    }

    public static function readTiledata() {
        $tiledata = UltimaPHP::$conf['muls']['location'] . "tiledata.mul";

        if (!is_file($tiledata)) {
            UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_READ_FAIL, [$tiledata]);
            UltimaPHP::stop();
        }

        self::$tiledata = [
            Others::TILEDATA_LAND   => [],
            Others::TILEDATA_STATIC => [],
        ];

        UltimaPHP::$files[Reader::FILE_TILEDATA] = new Reader($tiledata, Reader::FILE_TILEDATA);

        Functions::progressBar(0, 1, "Reading tiledata.mul");

        for ($i = 0; $i < 0x4000; $i++) {
            if (($i & 0x1F) == 0) {
                UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt32();
            }

            $blockInfo = [
                'type'    => 'land',
                'flags1'  => UltimaPHP::$files[Reader::FILE_TILEDATA]->readUInt32(),
                'flags2'  => UltimaPHP::$files[Reader::FILE_TILEDATA]->readUInt32(),
                'texture' => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt16(),
                'name'    => trim(Functions::readUnicodeStringSafe(str_split(Functions::strToHex(UltimaPHP::$files[Reader::FILE_TILEDATA]->read(20)), 2))),
            ];

            self::$tiledata[Others::TILEDATA_LAND][$i] = $blockInfo;
        }

        for ($i = 0; $i < 0x10000; $i++) {
            if (($i & 0x1F) == 0) {
                UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt32();
            }

            $blockInfo = [
                'type'      => 'static',
                'flags1'    => UltimaPHP::$files[Reader::FILE_TILEDATA]->readUInt32(),
                'flags2'    => UltimaPHP::$files[Reader::FILE_TILEDATA]->readUInt32(),
                'weight'    => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'quality'   => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'unknown1'  => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt16(),
                'unknown2'  => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'quantity'  => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'animation' => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt16(),
                'unknown3'  => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'hue'       => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'unknown4'  => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'value'     => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'height'    => UltimaPHP::$files[Reader::FILE_TILEDATA]->readInt8(),
                'name'      => Functions::readUnicodeStringSafe(str_split(Functions::strToHex(UltimaPHP::$files[Reader::FILE_TILEDATA]->read(20)), 2)),
            ];

            self::$tiledata[Others::TILEDATA_STATIC][$i] = $blockInfo;
        }

        Functions::progressBar(1, 1, "Reading tiledata.mul");
        self::readMaps();
    }

    /**
     * Render the maps inside chunk arrays
     */
    public static function readMaps() {
        for ($actualMap = 0; $actualMap < UltimaPHP::$conf["muls"]['maps']; $actualMap++) {
            /* Start reading the map files of actual map */
            Functions::progressBar(0, 1, "Reading map{$actualMap}.mul file");

            $mapFile = UltimaPHP::$conf['muls']['location'] . "map{$actualMap}.mul";
            $mapSize = explode(",", UltimaPHP::$conf["muls"]["map{$actualMap}"]);

            if (!is_file($mapFile)) {
                UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_READ_FAIL);
                UltimaPHP::stop();
            }

            if (!isset(UltimaPHP::$files[Reader::FILE_MAP_FILE])) {
                UltimaPHP::$files[Reader::FILE_MAP_FILE] = [];
            }

            UltimaPHP::$files[Reader::FILE_MAP_FILE][$actualMap] = new Reader($mapFile, Reader::FILE_MAP_FILE);

            $chunks_x = ceil($mapSize[0] / self::$chunkSize);
            $chunks_y = ceil($mapSize[1] / self::$chunkSize);

            // Build the array that will store map chunks
            for ($xChunk = 0; $xChunk < $chunks_x; $xChunk++) {
                self::$chunks[$actualMap][$xChunk] = [];
                for ($yChunk = 0; $yChunk < $chunks_y; $yChunk++) {
                    self::$chunks[$actualMap][$xChunk][$yChunk] = [];
                }
            }

            // Store information about the map muls and size
            self::$maps[$actualMap] = [
                'mul'  => null,
                'size' => [
                    'x' => null,
                    'y' => null,
                ],
            ];

            self::$mapSizes[$actualMap]['x']     = $mapSize[0];
            self::$mapSizes[$actualMap]['y']     = $mapSize[1];
            self::$maps[$actualMap]['mul']       = fopen($mapFile, "rb");
            self::$maps[$actualMap]['size']['x'] = (int) $mapSize[0] >> 3;
            self::$maps[$actualMap]['size']['y'] = (int) $mapSize[1] >> 3;

            Functions::progressBar(1, 1, "Reading map{$actualMap}.mul file");

            /* Start reading the staidx file of actual map */
            Functions::progressBar(0, 1, "Reading staidx{$actualMap}.mul file");

            $indexFile = UltimaPHP::$conf['muls']['location'] . "staidx{$actualMap}.mul";

            if (!is_file($indexFile)) {
                UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_READ_FAIL);
                UltimaPHP::stop();
            }

            if (!isset(UltimaPHP::$files[Reader::FILE_STATIC_INDEX])) {
                UltimaPHP::$files[Reader::FILE_STATIC_INDEX] = [];
            }

            UltimaPHP::$files[Reader::FILE_STATIC_INDEX][$actualMap] = new Reader($indexFile, Reader::FILE_MAP_FILE);

            Functions::progressBar(1, 1, "Reading staidx{$actualMap}.mul file");

            /* Start reading the statics file of actual map */
            Functions::progressBar(0, 1, "Reading statics{$actualMap}.mul file");
            $staticFile = UltimaPHP::$conf['muls']['location'] . "statics{$actualMap}.mul";

            if (!is_file($staticFile)) {
                UltimaPHP::setStatus(UltimaPHP::STATUS_FILE_READ_FAIL);
                UltimaPHP::stop();
            }

            if (!isset(UltimaPHP::$files[Reader::FILE_STATIC_FILE])) {
                UltimaPHP::$files[Reader::FILE_STATIC_FILE] = [];
            }
            UltimaPHP::$files[Reader::FILE_STATIC_FILE][$actualMap] = new Reader($staticFile, Reader::FILE_MAP_FILE);

            Functions::progressBar(1, 1, "Reading statics{$actualMap}.mul file");
        }
    }

    public static function getTerrainLand($p_x = 0, $p_y = 0, $map = 0) {
        if ($p_x == 0 || $p_y == 0) {
            return false;
        }

        if (!isset(UltimaPHP::$files[Reader::FILE_MAP_FILE][$map])) {
            return false;
        }

        $x = $p_x >> 3;
        $y = $p_y >> 3;

        $offset = ((($x * self::$maps[$map]['size']['y']) + $y) * 196) + 4;

        UltimaPHP::$files[Reader::FILE_MAP_FILE][$map]->setPosition($offset);

        for ($bx = 0; $bx < 8; $bx++) {
            for ($by = 0; $by < 8; $by++) {

                $info = [
                    'tile'     => UltimaPHP::$files[Reader::FILE_MAP_FILE][$map]->readInt16(),
                    'position' => [
                        'x' => ($x << 3) + $bx,
                        'y' => ($y << 3) + $by,
                        'z' => UltimaPHP::$files[Reader::FILE_MAP_FILE][$map]->readInt8(),
                    ],
                ];

                if (isset(self::$tiledata[Others::TILEDATA_LAND][$info['tile']]) && $info['position']['x'] == $p_x && $info['position']['y'] == $p_y) {
                    return array_merge(self::$tiledata[Others::TILEDATA_LAND][$info['tile']], ['tile' => $info['tile'], 'position' => $info['position']]);
                }
            }
        }

        return false;
    }

    public static function getTerrainStatics($p_x = 0, $p_y = 0, $map = 0) {
        if ($p_x == 0 || $p_y == 0) {
            return false;
        }

        if (!isset(UltimaPHP::$files[Reader::FILE_STATIC_INDEX][$map]) || !isset(UltimaPHP::$files[Reader::FILE_STATIC_FILE][$map])) {
            return false;
        }

        $x = $p_x >> 3;
        $y = $p_y >> 3;

        $offset = (($x * self::$maps[$map]['size']['y']) + $y) * 12;

        UltimaPHP::$files[Reader::FILE_STATIC_INDEX][$map]->setPosition($offset);

        $lookup = UltimaPHP::$files[Reader::FILE_STATIC_INDEX][$map]->readInt32();
        $length = UltimaPHP::$files[Reader::FILE_STATIC_INDEX][$map]->readInt32();

        if ($lookup <= 0 || $length <= 0) {
            return false;
        }

        UltimaPHP::$files[Reader::FILE_STATIC_FILE][$map]->setPosition($lookup);

        $tiles = [];
        for ($i = 0; $i <= $length / 7; $i++) {
            $info = [
                'tile'     => UltimaPHP::$files[Reader::FILE_STATIC_FILE][$map]->readInt16(),
                'position' => [
                    'x' => ($x << 3) + UltimaPHP::$files[Reader::FILE_STATIC_FILE][$map]->readUInt8(),
                    'y' => ($y << 3) + UltimaPHP::$files[Reader::FILE_STATIC_FILE][$map]->readUInt8(),
                    'z' => UltimaPHP::$files[Reader::FILE_STATIC_FILE][$map]->readInt8(),
                ],
                'hue'      => UltimaPHP::$files[Reader::FILE_STATIC_FILE][$map]->readInt16(),
            ];

            if (isset(self::$tiledata[Others::TILEDATA_STATIC][$info['tile']]) && $info['position']['x'] == $p_x && $info['position']['y'] == $p_y) {
                $tiles[] = array_merge(self::$tiledata[Others::TILEDATA_STATIC][$info['tile']], ['position' => $info['position'], 'hue' => $info['hue']]);
            }
        }

        if (count($tiles) == 0) {
            return false;
        }

        return $tiles;
    }

    /**
     * Creates a new serial based on last serial created
     */
    public static function newSerial($type = null) {
        if ($type === null) {
            return false;
        }

        self::$lastSerial[$type]++;
        return (isset(self::$serialData[self::$lastSerial[$type]]) ? self::newSerial($type) : self::$lastSerial[$type]);
    }

    /**
     * Return the chunk number of desired map position
     */
    public static function getChunk($pos_x = null, $pos_y = null) {
        if ($pos_x === null || $pos_y === null || $pos_x <= 0 || $pos_y <= 0) {
            return false;
        }

        return [
            'x' => (int) ceil($pos_x / self::$chunkSize),
            'y' => (int) ceil($pos_y / self::$chunkSize),
        ];
    }

    public static function removeSerialData($serial = null) {
        if ($serial === null) {
            return false;
        }

        if (!isset(self::$serialData[$serial])) {
            return false;
        }

        $instance = self::getBySerial($serial);
        $pos      = $instance->position;
        $chunk    = self::getChunk($pos['x'], $pos['y']);
        unset(self::$serialData[$serial]);
        unset(self::$chunks[$pos['map']][$chunk['x']][$chunk['y']][$serial]);
        return true;
    }

    /**
     * Add the player to into the map and store information inside the right chunk
     */
    public static function addPlayerToMap(Player $player) {
        $chunk = self::getChunk($player->position['x'], $player->position['y']);

        self::$chunks[$player->position['map']][$chunk['x']][$chunk['y']][(int) $player->serial] = [
            'type'     => "player",
            'client'   => $player->client,
            'instance' => null,
        ];

        self::$serialData[(int) $player->serial] = ['map' => $player->position['map'], 'x' => $chunk['x'], 'y' => $chunk['y']];

        return true;
    }

    /**
     *     Add the desired object into the map and store information inside the right chunk
     */
    public static function addObjectToMap(Object $object, $pos_x, $pos_y, $pos_z, $pos_m) {
        $object->position = [
            'x'       => $pos_x,
            'y'       => $pos_y,
            'z'       => $pos_z,
            'map'     => $pos_m,
            'facing'  => 0,
            'running' => 0,
        ];

        $chunk = self::getChunk($pos_x, $pos_y);

        self::$chunks[$pos_m][$chunk['x']][$chunk['y']][$object->serial] = [
            'type'     => 'object',
            'client'   => null,
            'instance' => $object,
        ];

        self::$serialData[$object->serial] = ['map' => $pos_m, 'x' => $chunk['x'], 'y' => $chunk['y']];

        self::updateChunk($chunk, false, $pos_m);

        return true;
    }

    public static function updateChunkForced($position = null) {
        if ($position === null) {
            return false;
        }

        $chunk = self::getChunk($position['x'], $position['y']);
        self::updateChunk($chunk, false, $position['map'], true);
        return true;
    }

    /**
     *     Add the desired object into the map and store information inside the right chunk
     */
    public static function addMobileToMap(Mobile $mobile, $pos_x, $pos_y, $pos_z, $pos_m) {
        $mobile->position = [
            'x'       => $pos_x,
            'y'       => $pos_y,
            'z'       => $pos_z,
            'map'     => $pos_m,
            'facing'  => random_int(0, 6),
            'running' => 0,
        ];
        $mobile->location = "map";

        $chunk = self::getChunk($pos_x, $pos_y);

        self::$chunks[$pos_m][$chunk['x']][$chunk['y']][$mobile->serial] = [
            'type'     => "mobile",
            'client'   => null,
            'instance' => $mobile,
        ];

        self::$serialData[$mobile->serial] = ['map' => $pos_m, 'x' => $chunk['x'], 'y' => $chunk['y']];

        self::updateChunk($chunk, false, $pos_m);

        return true;
    }

    public static function getBySerial($serial = false) {
        if ($serial === false) {
            return false;
        }

        $serial = (int) $serial;

        if (!isset(self::$serialData[$serial])) {
            return false;
        }

        $chunk = self::$serialData[$serial];
        $info  = self::$chunks[$chunk['map']][$chunk['x']][$chunk['y']][$serial];

        switch ($info['type']) {
        case 'player':
            return UltimaPHP::$socketClients[$info['client']]['account']->player;
            break;
        case 'mobile':
        case 'object':
            return $info['instance'];
            break;
        default:
            return false;
            break;
        }
    }

    /**
     * Send desired packet to a range of players around the client
     */
    public static function sendPacketRange($packet = null, $client) {
        if ($packet === null) {
            return false;
        }

        $actual_player = UltimaPHP::$socketClients[$client]['account']->player;

        $chunkInfo = self::getChunk($actual_player->position['x'], $actual_player->position['y']);
        $chunk     = self::$chunks[$actual_player->position['map']][$chunkInfo['x']][$chunkInfo['y']];

        $updateRange = [
            'from' => ['x' => ($actual_player->position['x'] - UltimaPHP::$conf['muls']['render_range']), 'y' => ($actual_player->position['y'] - UltimaPHP::$conf['muls']['render_range'])],
            'to'   => ['x' => ($actual_player->position['x'] + UltimaPHP::$conf['muls']['render_range']), 'y' => ($actual_player->position['y'] + UltimaPHP::$conf['muls']['render_range'])],
        ];

        foreach ($chunk as $serial => $data) {
            if ($data['type'] != "player") {
                continue;
            }

            $player = UltimaPHP::$socketClients[$data['client']]['account']->player;

            if ($actual_player->serial != $player->serial && $player->position['x'] >= $updateRange['from']['x'] && $player->position['x'] <= $updateRange['to']['x'] && $player->position['y'] >= $updateRange['from']['y'] && $player->position['y'] <= $updateRange['to']['y']) {
                Sockets::out($player->client, $packet, false);
            }
        }
    }

    /**
     * Send desired packet to a range of players around desired position
     */
    public static function sendPacketRangePosition($packet = null, $position = null) {
        if ($packet === null || $position === null) {
            return false;
        }

        $chunkInfo = self::getChunk($position['x'], $position['y']);
        $chunk     = self::$chunks[$position['map']][$chunkInfo['x']][$chunkInfo['y']];

        $updateRange = [
            'from' => ['x' => ($position['x'] - UltimaPHP::$conf['muls']['render_range']), 'y' => ($position['y'] - UltimaPHP::$conf['muls']['render_range'])],
            'to'   => ['x' => ($position['x'] + UltimaPHP::$conf['muls']['render_range']), 'y' => ($position['y'] + UltimaPHP::$conf['muls']['render_range'])],
        ];

        foreach ($chunk as $serial => $data) {
            if ($data['type'] != "player") {
                continue;
            }

            $player = UltimaPHP::$socketClients[$data['client']]['account']->player;
            if ($player->position['x'] >= $updateRange['from']['x'] && $player->position['x'] <= $updateRange['to']['x'] && $player->position['y'] >= $updateRange['from']['y'] && $player->position['y'] <= $updateRange['to']['y']) {
                Sockets::out($player->client, $packet, false);
            }
        }
    }

    /**
     * Update players with objects from desired chunk
     * $map is only used in case of the request come from a mobile or items
     */
    //self::updateChunk($chunk, false, $position['map'], true);
    public static function updateChunk($chunk = null, $client = false, $map = null, $forceItemUpdate = false) {
        if ($chunk === null && $client !== false) {
            $chunk = self::getChunk(UltimaPHP::$socketClients[$client]['account']->player->position['x'], UltimaPHP::$socketClients[$client]['account']->player->position['y']);
            $map   = UltimaPHP::$socketClients[$client]['account']->player->position['map'];
        }

        if ($chunk === null) {
            UltimaPHP::log("Server tryied to update an invalid chunk", UltimaPHP::LOG_WARNING);
            return false;
        }

        if ($map === null) {
            UltimaPHP::log("Server tryied to update an chunk form invalid map", UltimaPHP::LOG_WARNING);
            return false;
        }

        $chunkData = self::$chunks[$map][$chunk['x']][$chunk['y']];

        /* Loop trought every player and updates everything in view range */
        foreach ($chunkData as $serial => $data) {
            if ($data['type'] != "player") {
                continue;
            }

            $actual_player        = UltimaPHP::$socketClients[$data['client']]['account']->player;
            $actual_player_plevel = UltimaPHP::$socketClients[$data['client']]['account']->plevel;

            $updateRange = [
                'from' => ['x' => ($actual_player->position['x'] - $actual_player->render_range), 'y' => ($actual_player->position['y'] - $actual_player->render_range)],
                'to'   => ['x' => ($actual_player->position['x'] + $actual_player->render_range), 'y' => ($actual_player->position['y'] + $actual_player->render_range)],
            ];

            /* Remove objects that was removed from player view */
            foreach ($actual_player->mapRange as $serialTest => $active) {
                $instance = Map::getBySerial($serialTest);
                if (!$instance || $instance->instanceType != UltimaPHP::INSTANCE_PLAYER) {
                    $actual_player->removeObjectFromView($serialTest);
                }
            }

            /* Loop trought every items and mobiles to update on player view */
            foreach ($chunkData as $serialTest => $dataTest) {
                if ($dataTest['type'] != "player") {
                    /* Do not send draw packets again if object is allready in player view */
                    if ($serialTest == $serial) {
                        continue;
                    }

                    /* If mobile/item leaves player map view range, removes */
                    if ($forceItemUpdate == true || (isset($actual_player->mapRange[$serialTest]) && (!Functions::inRangeView($dataTest['instance']->position, $updateRange) || !isset(self::$serialData[$serialTest])))) {
                        $actual_player->removeObjectFromView($serialTest);

                        if ($forceItemUpdate == false) {
                            continue;
                        }
                    }

                    if ($forceItemUpdate == true || (!isset($actual_player->mapRange[$serialTest]) && Functions::inRangeView($dataTest['instance']->position, $updateRange))) {
                        $dataTest['instance']->draw($actual_player->client);
                    }
                } else {
                    $player        = UltimaPHP::$socketClients[$dataTest['client']]['account']->player;
                    $player_plevel = UltimaPHP::$socketClients[$dataTest['client']]['account']->plevel;

                    /* Remove running flags if player stoped */
                    if (UltimaPHP::$socketClients[$dataTest['client']]['account']->player->position['running'] == true && (time() - UltimaPHP::$socketClients[$dataTest['client']]['account']->player->lastMove) > 2) {
                        UltimaPHP::$socketClients[$dataTest['client']]['account']->player->position['running'] = false;
                    }

                    if ($player->hidden && $actual_player_plevel < $player_plevel) {
                        if (isset($actual_player->mapRange[$player->serial])) {
                            $actual_player->removeObjectFromView($player->serial);
                        }
                        continue;
                    }

                    if ($actual_player->serial != $player->serial) {
                        if (!isset($actual_player->mapRange[$player->serial]) || (time() - $actual_player->mapRange[$player->serial]['lastupdate']) > 2 || $player->forceUpdate == true) {
                            $actual_player->mapRange[$player->serial] = [
                                'status'     => true,
                                'lastupdate' => time(),
                            ];
                            $actual_player->drawChar(false, $player->client);
                        }

                        $actual_player->updatePlayer($player->client);
                    }
                }
            }
        }

        foreach ($chunkData as $serial => $data) {
            if ($data['type'] == "player") {
                UltimaPHP::$socketClients[$data['client']]['account']->player->forceUpdate = false;
            }
        }

        return true;
    }

}
