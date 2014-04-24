<?php
namespace Bread\Push\Device;

use Bread\Configuration\Manager as Configuration;
use Bread\REST;

class Model extends REST\Model
{

    const TYPE_ANDROID = "android";

    const TYPE_IOS = "ios";

    protected $itemId;

    protected $type;

    protected $aro;

    protected $uuid;

}

Configuration::defaults('Bread\Push\Device\Model', array(
    'properties' => array(
        'itemId' => array(
            'type' => 'string',
            'unique' => true
        ),
        'type' => array(
            'type' => 'string'
        ),
        'aro' => array(
            'type' => 'Bread\REST\Behaviors\ARO\Authenticated'
        ),
        'uuid' => array(
            'type' => 'string'
        )
    )
));