<?php

return [

    /*
    | Promoter ("RA") types.
    */
    'types' => [
        'conditional_ra' => 'Conditional RA',
        'real_ra' => 'Real RA',
    ],

    /*
    | Which date a promoter's monthly achievement is counted on.
    | activation_date = devices activated (sold out to the customer) that month.
    | st_date        = devices billed to the retailer (sell-through) that month.
    */
    'achievement_basis' => env('PROMOTER_ACHIEVEMENT_BASIS', 'activation_date'),

];
