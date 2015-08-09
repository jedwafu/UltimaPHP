<?php

/**
 * Ultima PHP - OpenSource Ultima Online Server written in PHP
 * Version: 0.1 - Pre Alpha
 */
class Sockets
{
    function __construct() {
        
        // Create a TCP Stream socket
        if (false == (UltimaPHP::$socketServer = @socket_create(AF_INET, SOCK_STREAM, 0))) {
            UltimaPHP::log("Could not start socket listening.", UltimaPHP::LOG_DANGER);
            UltimaPHP::stop();
        }
        
        if (!socket_set_nonblock(UltimaPHP::$socketServer)) {
        	echo "???";
        }
        
        if (socket_bind(UltimaPHP::$socketServer, UltimaPHP::$conf['server']['ip'], UltimaPHP::$conf['server']['port'])) {
            UltimaPHP::setStatus(UltimaPHP::STATUS_LISTENING, array(UltimaPHP::$conf['server']['ip'], UltimaPHP::$conf['server']['port']));
        } 
        else {
            UltimaPHP::log("Server could not listen on " . UltimaPHP::$conf['server']['ip'] . " at port " . UltimaPHP::$conf['server']['port'], UltimaPHP::LOG_DANGER);
            UltimaPHP::stop();
        }
        socket_listen(UltimaPHP::$socketServer);
    }
    
    public static function monitor() {
        $microtime = microtime(true);
        if (UltimaPHP::$socketClients[(string)$microtime]['socket'] = @socket_accept(UltimaPHP::$socketServer)) {
            // Create the socket between the client and the server
            $id = (string)$microtime;
            socket_getpeername(UltimaPHP::$socketClients[$id]['socket'], UltimaPHP::$socketClients[$id]['ip'], UltimaPHP::$socketClients[$id]['port']);
            UltimaPHP::$socketClients[$id]['LastInput'] = $microtime;
            UltimaPHP::$socketClients[$id]['packets'] = array();
        }

        foreach (UltimaPHP::$socketClients as $client => $socket) {
            if (isset($socket) && isset($socket['socket']) && $socket['socket'] != null) {
                $input = @socket_read($socket['socket'], 8192);
                if (strlen($input) > 0) {
                    self::in($input, $client);
                    UltimaPHP::$socketClients[$client]['LastInput'] = $microtime;
                }

                foreach ($socket['packets'] as $packet_id => $packet) {
                    if ($packet['time'] <= $microtime) {
                        $err = NULL;
                        socket_write($socket['socket'], $packet['packet']) or $err = socket_last_error(UltimaPHP::$socketClients[$client]['socket']);
                        if ($err === NULL) {
                            unset(UltimaPHP::$socketClients[$client]['packets'][$packet_id]);
                        }
                    }
                }
            }
        }
    }
    
    private static function in($input, $client) {
        $packet = "packet_0x" . strtoupper(dechex(ord(substr($input, 0, 1))));
        $len = strlen($input);
        $data = dechex(ord(substr($input, 0, 1)));

        for ($i = 1; $i <= $len; $i++) {
            $ch = dechex(ord(substr($input, $i, 1)));
            
            if (strlen($ch) < 2) {
                $data.= " 0$ch";
            } 
            else {
                $data.= " $ch";
            }
        }

        if (method_exists("Packets", $packet)) {
            Packets::$packet(explode(" ", $data), $client);
        } 
        else {
            UltimaPHP::log("Client sent an unknow packet 0x" . strtoupper(dechex(ord(substr($input, 0, 1)))) . " to the server:", UltimaPHP::LOG_WARNING);
            UltimaPHP::log("Packet received: " . $data, UltimaPHP::LOG_NORMAL);
        }
    }
    
    public static function out($client, $packet, $dontConvert = false) {
        $err = NULL;

        if ($dontConvert === false) {
        	$packet = Functions::hexToChr($packet);
		} else {
			$packet = $packet;
		}

        UltimaPHP::$socketClients[$client]['packets'][] = array('packet' => $packet, 'time' => (microtime(true) + 0.00100));
    }
    
    public static function addEvent($client, $event, $time) {
        $mt = microtime(true);
        if (!is_array($event)) {
            UltimaPHP::log("Unknow event was send to the server.", UltimaPHP::LOG_WARNING);
            return false;
        } 
        else {
            UltimaPHP::$socketEvents[$mt] = array('event' => $event, 'client' => $client, 'time' => ($mt + $time));
            return true;
        }
    }
    
    public static function runEvents() {
        $mt = microtime(true);
        foreach (UltimaPHP::$socketEvents as $registerTime => $event) {
            if ($mt >= $event['time']) {
            	if (class_exists($event['event']['class'])) {
            		if (method_exists($event['event']['class'], $event['event']['method'])) {
            			$event['event']['args'] = (isset($event['event']['args']) ? $event['event']['args'] : array());

            			call_user_func_array(array($event['event']['class'], $event['event']['method']), array("", $event['client'], $event['event']['args']));
            			
            			unset(UltimaPHP::$socketEvents[$registerTime]);
            		} else {
            			// Event method called don't exists
            			UltimaPHP::log("Event called a invalid method: " . $event['event']['method'] . " from class: " . $event['event']['class'], UltimaPHP::LOG_WARNING);
            			unset(UltimaPHP::$socketEvents[$registerTime]);
            		}
            	} else {
            		// Event class called
            		UltimaPHP::log("Event called a invalid class: " . $event['event']['class'], UltimaPHP::LOG_WARNING);
            		unset(UltimaPHP::$socketEvents[$registerTime]);
            	}
            }
        }
    }

    public static function compress($string) {
    	// Sphere core huffman table (What to do now?)
    	$huffmanTable = array(0x2, 0x000, 0x5, 0x01F, 0x6, 0x022, 0x7, 0x034, 0x7, 0x075, 0x6, 0x028, 0x6, 0x03B, 0x7, 0x032, 0x8, 0x0E0, 0x8, 0x062, 0x7, 0x056, 0x8, 0x079, 0x9, 0x19D, 0x8, 0x097, 0x6, 0x02A, 0x7, 0x057, 0x8, 0x071, 0x8, 0x05B, 0x9, 0x1CC, 0x8, 0x0A7, 0x7, 0x025, 0x7, 0x04F, 0x8, 0x066, 0x8, 0x07D, 0x9, 0x191, 0x9, 0x1CE, 0x7, 0x03F, 0x9, 0x090, 0x8, 0x059, 0x8, 0x07B, 0x8, 0x091, 0x8, 0x0C6, 0x6, 0x02D, 0x9, 0x186, 0x8, 0x06F, 0x9, 0x093, 0xA, 0x1CC, 0x8, 0x05A, 0xA, 0x1AE, 0xA, 0x1C0, 0x9, 0x148, 0x9, 0x14A, 0x9, 0x082, 0xA, 0x19F, 0x9, 0x171, 0x9, 0x120, 0x9, 0x0E7, 0xA, 0x1F3, 0x9, 0x14B, 0x9, 0x100, 0x9, 0x190, 0x6, 0x013, 0x9, 0x161, 0x9, 0x125, 0x9, 0x133, 0x9, 0x195, 0x9, 0x173, 0x9, 0x1CA, 0x9, 0x086, 0x9, 0x1E9, 0x9, 0x0DB, 0x9, 0x1EC, 0x9, 0x08B, 0x9, 0x085, 0x5, 0x00A, 0x8, 0x096, 0x8, 0x09C, 0x9, 0x1C3, 0x9, 0x19C, 0x9, 0x08F, 0x9, 0x18F, 0x9, 0x091, 0x9, 0x087, 0x9, 0x0C6, 0x9, 0x177, 0x9, 0x089, 0x9, 0x0D6, 0x9, 0x08C, 0x9, 0x1EE, 0x9, 0x1EB, 0x9, 0x084, 0x9, 0x164, 0x9, 0x175, 0x9, 0x1CD, 0x8, 0x05E, 0x9, 0x088, 0x9, 0x12B, 0x9, 0x172, 0x9, 0x10A, 0x9, 0x08D, 0x9, 0x13A, 0x9, 0x11C, 0xA, 0x1E1, 0xA, 0x1E0, 0x9, 0x187, 0xA, 0x1DC, 0xA, 0x1DF, 0x7, 0x074, 0x9, 0x19F, 0x8, 0x08D, 0x8, 0x0E4, 0x7, 0x079, 0x9, 0x0EA, 0x9, 0x0E1, 0x8, 0x040, 0x7, 0x041, 0x9, 0x10B, 0x9, 0x0B0, 0x8, 0x06A, 0x8, 0x0C1, 0x7, 0x071, 0x7, 0x078, 0x8, 0x0B1, 0x9, 0x14C, 0x7, 0x043, 0x8, 0x076, 0x7, 0x066, 0x7, 0x04D, 0x9, 0x08A, 0x6, 0x02F, 0x8, 0x0C9, 0x9, 0x0CE, 0x9, 0x149, 0x9, 0x160, 0xA, 0x1BA, 0xA, 0x19E, 0xA, 0x39F, 0x9, 0x0E5, 0x9, 0x194, 0x9, 0x184, 0x9, 0x126, 0x7, 0x030, 0x8, 0x06C, 0x9, 0x121, 0x9, 0x1E8, 0xA, 0x1C1, 0xA, 0x11D, 0xA, 0x163, 0xA, 0x385, 0xA, 0x3DB, 0xA, 0x17D, 0xA, 0x106, 0xA, 0x397, 0xA, 0x24E, 0x7, 0x02E, 0x8, 0x098, 0xA, 0x33C, 0xA, 0x32E, 0xA, 0x1E9, 0x9, 0x0BF, 0xA, 0x3DF, 0xA, 0x1DD, 0xA, 0x32D, 0xA, 0x2ED, 0xA, 0x30B, 0xA, 0x107, 0xA, 0x2E8, 0xA, 0x3DE, 0xA, 0x125, 0xA, 0x1E8, 0x9, 0x0E9, 0xA, 0x1CD, 0xA, 0x1B5, 0x9, 0x165, 0xA, 0x232, 0xA, 0x2E1, 0xB, 0x3AE, 0xB, 0x3C6, 0xB, 0x3E2, 0xA, 0x205, 0xA, 0x29A, 0xA, 0x248, 0xA, 0x2CD, 0xA, 0x23B, 0xB, 0x3C5, 0xA, 0x251, 0xA, 0x2E9, 0xA, 0x252, 0x9, 0x1EA, 0xB, 0x3A0, 0xB, 0x391, 0xA, 0x23C, 0xB, 0x392, 0xB, 0x3D5, 0xA, 0x233, 0xA, 0x2CC, 0xB, 0x390, 0xA, 0x1BB, 0xB, 0x3A1, 0xB, 0x3C4, 0xA, 0x211, 0xA, 0x203, 0x9, 0x12A, 0xA, 0x231, 0xB, 0x3E0, 0xA, 0x29B, 0xB, 0x3D7, 0xA, 0x202, 0xB, 0x3AD, 0xA, 0x213, 0xA, 0x253, 0xA, 0x32C, 0xA, 0x23D, 0xA, 0x23F, 0xA, 0x32F, 0xA, 0x11C, 0xA, 0x384, 0xA, 0x31C, 0xA, 0x17C, 0xA, 0x30A, 0xA, 0x2E0, 0xA, 0x276, 0xA, 0x250, 0xB, 0x3E3, 0xA, 0x396, 0xA, 0x18F, 0xA, 0x204, 0xA, 0x206, 0xA, 0x230, 0xA, 0x265, 0xA, 0x212, 0xA, 0x23E, 0xB, 0x3AC, 0xB, 0x393, 0xB, 0x3E1, 0xA, 0x1DE, 0xB, 0x3D6, 0xA, 0x31D, 0xB, 0x3E5, 0xB, 0x3E4, 0xA, 0x207, 0xB, 0x3C7, 0xA, 0x277, 0xB, 0x3D4, 0x8, 0x0C0, 0xA, 0x162, 0xA, 0x3DA, 0xA, 0x124, 0xA, 0x1B4, 0xA, 0x264, 0xA, 0x33D, 0xA, 0x1D1, 0xA, 0x1AF, 0xA, 0x39E, 0xA, 0x24F, 0xB, 0x373, 0xA, 0x249, 0xB, 0x372, 0x9, 0x167, 0xA, 0x210, 0xA, 0x23A, 0xA, 0x1B8, 0xB, 0x3AF, 0xA, 0x18E, 0xA, 0x2EC, 0x7, 0x062, 0x4, 0x00D);
    	// What to do with it????
    	return $string;
    }
}
?>