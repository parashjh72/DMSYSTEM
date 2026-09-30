<?php

return [

    'timezone' => env('PJP_TIMEZONE', env('REPORTS_TIMEZONE', 'Asia/Kathmandu')),

    /*
    | Beat plan lifecycle. ASM approval is NOT final — only the NSM final-approves.
    */
    'statuses' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'asm_review' => 'ASM review',
        'revision_required' => 'Revision required',
        'resubmitted' => 'Resubmitted',
        'asm_approved' => 'ASM approved',
        'forwarded_to_nsm' => 'Forwarded to NSM',
        'nsm_review' => 'NSM review',
        'final_approved' => 'NSM final approved',
        'rejected' => 'Rejected',
    ],

    /** Statuses in which the TSO may still edit the plan. */
    'editable_statuses' => ['draft', 'revision_required'],

    /*
    | Per-day status the TSO sets on the planner.
    */
    'day_statuses' => [
        'planned' => 'Planned',
        'leave' => 'Leave',
        'weekly_off' => 'Weekly Off',
        'holiday' => 'Holiday',
        'no_plan' => 'No Plan',
    ],

    /*
    | Why a beat visit ended without an order (a "non-effective" visit).
    */
    'no_order_reasons' => [
        'stock_sufficient' => 'Stock sufficient',
        'owner_unavailable' => 'Owner / buyer not available',
        'shop_closed' => 'Shop closed',
        'payment_pending' => 'Payment / credit pending',
        'price_issue' => 'Price or margin issue',
        'scheme_issue' => 'Waiting for scheme / offer',
        'competitor' => 'Prefers competitor brand',
        'low_demand' => 'Low market demand',
        'other' => 'Other',
    ],
];
