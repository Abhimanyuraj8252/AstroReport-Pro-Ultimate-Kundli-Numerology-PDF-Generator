<?php
/**
 * Compatibility layer for PHP environments missing the cURL extension.
 * Defines necessary constants to prevent TCPDF from crashing.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if cURL constants are defined. If not, define them to prevent fatal errors.
// These values are standard integers used by cURL.

if (!defined('CURLOPT_URL'))
    define('CURLOPT_URL', 10002);
if (!defined('CURLOPT_RETURNTRANSFER'))
    define('CURLOPT_RETURNTRANSFER', 19913);
if (!defined('CURLOPT_BINARYTRANSFER'))
    define('CURLOPT_BINARYTRANSFER', 19914);
if (!defined('CURLOPT_TIMEOUT'))
    define('CURLOPT_TIMEOUT', 13);
if (!defined('CURLOPT_CONNECTTIMEOUT'))
    define('CURLOPT_CONNECTTIMEOUT', 156);
if (!defined('CURLOPT_USERAGENT'))
    define('CURLOPT_USERAGENT', 10018);
if (!defined('CURLOPT_HEADER'))
    define('CURLOPT_HEADER', 42);
if (!defined('CURLOPT_FOLLOWLOCATION'))
    define('CURLOPT_FOLLOWLOCATION', 52);
if (!defined('CURLOPT_MAXREDIRS'))
    define('CURLOPT_MAXREDIRS', 68);
if (!defined('CURLOPT_SSL_VERIFYPEER'))
    define('CURLOPT_SSL_VERIFYPEER', 64);
if (!defined('CURLOPT_SSL_VERIFYHOST'))
    define('CURLOPT_SSL_VERIFYHOST', 81);
if (!defined('CURLOPT_FAILONERROR'))
    define('CURLOPT_FAILONERROR', 45);
if (!defined('CURLOPT_HTTP_VERSION'))
    define('CURLOPT_HTTP_VERSION', 84);
if (!defined('CURLOPT_PROTOCOLS'))
    define('CURLOPT_PROTOCOLS', 181);
if (!defined('CURLOPT_REDIR_PROTOCOLS'))
    define('CURLOPT_REDIR_PROTOCOLS', 182);

// Info Constants
if (!defined('CURLINFO_HTTP_CODE'))
    define('CURLINFO_HTTP_CODE', 2097154);

// Protocol Constants (Bitmask)
if (!defined('CURLPROTO_HTTP'))
    define('CURLPROTO_HTTP', 1);
if (!defined('CURLPROTO_HTTPS'))
    define('CURLPROTO_HTTPS', 2);
if (!defined('CURLPROTO_FTP'))
    define('CURLPROTO_FTP', 4);
if (!defined('CURLPROTO_FTPS'))
    define('CURLPROTO_FTPS', 8);
if (!defined('CURLPROTO_SCP'))
    define('CURLPROTO_SCP', 16);
if (!defined('CURLPROTO_SFTP'))
    define('CURLPROTO_SFTP', 32);
if (!defined('CURLPROTO_TELNET'))
    define('CURLPROTO_TELNET', 64);
if (!defined('CURLPROTO_LDAP'))
    define('CURLPROTO_LDAP', 128);
if (!defined('CURLPROTO_LDAPS'))
    define('CURLPROTO_LDAPS', 256);
if (!defined('CURLPROTO_DICT'))
    define('CURLPROTO_DICT', 512);
if (!defined('CURLPROTO_FILE'))
    define('CURLPROTO_FILE', 1024);
if (!defined('CURLPROTO_TFTP'))
    define('CURLPROTO_TFTP', 2048);
if (!defined('CURLPROTO_IMAP'))
    define('CURLPROTO_IMAP', 4096);
if (!defined('CURLPROTO_IMAPS'))
    define('CURLPROTO_IMAPS', 8192);
if (!defined('CURLPROTO_POP3'))
    define('CURLPROTO_POP3', 16384);
if (!defined('CURLPROTO_POP3S'))
    define('CURLPROTO_POP3S', 32768);
if (!defined('CURLPROTO_SMTP'))
    define('CURLPROTO_SMTP', 65536);
if (!defined('CURLPROTO_SMTPS'))
    define('CURLPROTO_SMTPS', 131072);
if (!defined('CURLPROTO_RTSP'))
    define('CURLPROTO_RTSP', 262144);
if (!defined('CURLPROTO_RTMP'))
    define('CURLPROTO_RTMP', 524288);
if (!defined('CURLPROTO_RTMPT'))
    define('CURLPROTO_RTMPT', 1048576);
if (!defined('CURLPROTO_RTMPE'))
    define('CURLPROTO_RTMPE', 2097152);
if (!defined('CURLPROTO_RTMPTE'))
    define('CURLPROTO_RTMPTE', 4194304);
if (!defined('CURLPROTO_SMB'))
    define('CURLPROTO_SMB', 8388608);
if (!defined('CURLPROTO_SMBS'))
    define('CURLPROTO_SMBS', 16777216);
if (!defined('CURLPROTO_GOPHER'))
    define('CURLPROTO_GOPHER', 33554432);
if (!defined('CURLPROTO_MQTT'))
    define('CURLPROTO_MQTT', 67108864);
