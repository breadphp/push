<?php
namespace Bread\Push\Notification;

use Bread\Push\Device;
use Bread\Configuration\Manager as Configuration;
use Exception;

class Controller
{

    const URL_ANDROID = 'https://android.googleapis.com/gcm/send';

    const URL_IOS = 'ssl://gateway.sandbox.apple.com:2195';

    private $devices;

    private $domain;

    public function __construct($devices, $domain = '__default__')
    {
        $this->devices = $devices;
        $this->domain = $domain;
    }

    public function notify($message = null, $badge = null, $sound = null, $fields = array())
    {
        $android = array();
        $ios = array();
        foreach ($this->devices as $device) {
            switch ($device->type) {
                case Device\Model::TYPE_ANDROID:
                    $android[] = $device->itemId;
                    break;
                case Device\Model::TYPE_IOS:
                    $ios[] = $device->itemId;
                    break;
            }
        }
        if ($android) {
            return $this->notifyAndroid($android, $message, $badge, $sound, $fields);
        }
        if ($ios) {
            return $this->notifyIos($ios, $message, $badge, $sound, $fields);
        }
    }

    protected function notifyAndroid($devices, $message, $badge, $sound, $data = array())
    {
        if (null === $message) {
            return false;
        }
        $data['message'] = $message;
        $fields = array(
            'registration_ids' => (array) $devices,
            'data' => $data
        );
        $payload = json_encode($fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $ch = curl_init(self::URL_ANDROID);
        if (Configuration::get(__CLASS__, 'proxy', $this->domain)) {
            curl_setopt($ch, CURLOPT_PROXY, Configuration::get(__CLASS__, 'proxy', $this->domain));
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getAndroidHeaders(Configuration::get(__CLASS__, 'android.apikey', $this->domain)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $result = curl_exec($ch);
        if ($result === false) {
            error_log(curl_error($ch));
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    protected function notifyIos($devices, $message, $badge, $sound, $data = array())
    {
        $stream = stream_context_create();
        stream_context_set_option($stream, 'ssl', 'local_cert', Configuration::get(__CLASS__, 'ios.local_cert', $this->domain));
        stream_context_set_option($stream, 'ssl', 'passphrase', Configuration::get(__CLASS__, 'ios.passphrase', $this->domain));
        $fp = stream_socket_client(self::URL_IOS, $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $stream);
        if (!$fp) {
            throw new Exception("Failed to connect: $err $errstr" . PHP_EOL);
        }
        $data['aps'] = array();
        if (is_string($message)) {
            $data['aps']['alert'] = $message;
        }
        if (is_int($badge)) {
            $data['aps']['badge'] = $badge;
        }
        if (is_string($sound) || is_string($message)) {
            $data['aps']['sound'] = is_string($sound) ? $sound : 'default';
        }
        $payload = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $msg = '';
        foreach ($devices as $device) {
            $msg .= chr(0)
                . pack('n', 32)
                . pack('H*', $device)
                . pack('n', strlen($payload))
                . $payload;
        }
        $result = fwrite($fp, $msg, strlen($msg));
        fclose($fp);
        return $result;
    }

    protected function getAndroidHeaders($apikey)
    {
        return array(
            'Authorization: key=' . $apikey,
            'Content-Type: application/json'
        );
    }
}
