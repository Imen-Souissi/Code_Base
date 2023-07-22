<?php
return array(
    'ticket' => array(
        array(
            'name' => 'service-request',
            'description' => 'Service requests search result',
            'columns' => array(
                array(
                    'field' => 'number',
                    'display' => 'Number',
                    'show' => 'DYNAMIC',
                ),
                array(
                    'field' => 'title',
                    'display' => 'Title',
                    'show' => 'DYNAMIC',
                ),
                array(
                    'field' => 'status',
                    'display' => 'Status',
                    'show' => 'DYNAMIC',
                ),
                array(
                    'field' => 'contact',
                    'display' => 'Contact',
                    'show' => 'DYNAMIC',
                ),
                array(
                    'field' => 'submit_date',
                    'display' => 'Submitted On',
                    'show' => 'DYNAMIC',
                ),
            ),
        ),
    ),
);
?>