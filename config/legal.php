<?php

// Operator identity for the legal notice and privacy policy; null lines are not rendered.
return [
    'name' => env('LEGAL_OPERATOR_NAME', 'Maurice Hildebrandt'),
    'address' => env('LEGAL_OPERATOR_ADDRESS'),
    'email' => env('LEGAL_OPERATOR_EMAIL'),
];
