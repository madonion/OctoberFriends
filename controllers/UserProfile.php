<?php namespace DMA\Friends\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * UserProfile Back-end Controller
 */
class UserProfile extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('DMA.Friends', 'friends', 'userprofile');
    }
}