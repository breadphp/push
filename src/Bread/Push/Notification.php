<?php
namespace Bread\Push;

use Bread\Configuration\Manager as Configuration;
use Exception;

class Notification
{

    const URL_ANDROID = 'https://android.googleapis.com/gcm/send';

    const URL_IOS = 'ssl://gateway.sandbox.push.apple.com:2195';

    private $proxy;

    private $devices;

    public function __construct($devices)
    {
        $this->devices = $devices;
        $this->proxy = Configuration::get(__CLASS__, 'push.proxy');
    }

    public function notify($message = '')
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
            return $this->notifyAndroid($android, $message);
        }
        if ($ios) {
            return $this->notifyIos($ios, $message);
        }
    }

    protected function notifyAndroid($devices, $message)
    {
        $ch = curl_init(self::URL_ANDROID);
        if (isset($this->proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getAndroidHeaders(Configuration::get(__CLASS__, 'push.android.apikey')));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getPostFields($devices, $message));
        $result = curl_exec($ch);
        if ($result === false) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    protected function notifyIos($devices, $message)
    {
        $stream = stream_context_create();
        stream_context_set_option($stream, 'ssl', 'local_cert', Configuration::get(__CLASS__, 'push.ios.local_cert'));
        stream_context_set_option($stream, 'ssl', 'passphrase', Configuration::get(__CLASS__, 'push.ios.passphrase'));
        $fp = stream_socket_client(self::URL_IOS, $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $stream);
        if (!$fp) {
            throw new Exception("Failed to connect: $err $errstr" . PHP_EOL);
        }
        $body['aps'] = array(
            'alert' => $message
        );
        $payload = json_encode($body, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $msg = '';
        foreach($devices as $device) {
            $msg = chr(0)
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
        return [
            'Authorization: key=' . $apikey,
            'Content-Type: application/json'
        ];
    }

    protected function getPostFields($regId, $data, $datatype = "message")
    {
        $fields = array(
            'registration_ids' => (array) $regId,
            'data' => array(
                $datatype => $data
            )
        );
        return json_encode($fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }
}
