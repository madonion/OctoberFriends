<?php

namespace DMA\Friends\FormWidgets;

use Backend\Classes\FormWidgetBase;
use DMA\Friends\Models\Location;
use RainLab\User\Models\User;
use DMA\Friends\Classes\PrintManager;
use Flash;
use Lang;

/**
 * Activity Type Widget
 * 
 * This widget provides form elements for managing 
 * custom Activities implemented by friends and 3rd party plugins 
 * 
 * @package dma\friends
 * @author Kristen Arnold
 */
class PrintMembershipCard extends FormWidgetBase
{
    public $previewMode = false;

        /**
     * @var string If the field element names should be contained in an array.
     * Eg: <input name="nameArray[fieldName]" />
     */
    public $arrayName = true;

    /** 
     * {@inheritDoc}
     */
    public function widgetDetails()
    {
        return [
            'name'        => 'Print Membership Card',
            'description' => 'Prints a new membership card'
        ];
    }

    /** 
     * {@inheritDoc}
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('widget');
    }

    /**
     * {@inheritDoc}
     */
    public function prepareVars()
    {
        $locations = Location::hasMemberPrinter()->get();

        $options[] = '<option value="">Select One</option>';

        foreach ($locations as $location) {
            $options[] = '<option value="'. $location->id . '">'. $location->title . '</option>';
        }

        $this->vars['locationOptions'] = $options;
    }

    public function onPrintCard()
    {
        $locationId = post('printerLocation');
        if (empty($locationId)) {
            Flash::error('Select a location to print the membership card');
            return;
        }

        $location = Location::find($locationId)->first();
        $user = post('User');
        $user = User::where('name', '=', $user['name'])->first();

        $manager = new PrintManager($location, $user);
        $manager->printIdCard();
        Flash::info(Lang::get('dma.friends::lang.user.memberCard', ['title' => $location->title]));
    }
}
