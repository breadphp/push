<?php
namespace Bread\Push\Notification;

use Bread\REST;
use Bread\Configuration\Manager as Configuration;

class Model extends REST\Model
{

    protected $id;

    protected $aco;

    protected $aro;

    protected $unseen;
}

Configuration::defaults('Bread\Push\Notification\Model', array(
    'properties' => array(
        'id' => array(
            'type' => 'integer',
            'unique' => true,
            'strategy' => 'autoincrement'
        ),
        'aco' => array(
            'type' => 'Bread\REST\Behaviors\ACO'
        ),
        'aro' => array(
            'type' => 'Bread\REST\Behaviors\ARO\Authenticated'
        ),
        'unseen' => array(
            'type' => 'integer',
            'default' => 0
        )
    )
));