<?php

require 'PhpRtBridge.php';


// The values below make sense in the instance I was running the tests.
// You need to set the values that make sense to your case
$email = 'customer1@localhost';

// A ticket should be created previously with the user having $email
// as requestor
$TicketId = 12;

// TransactionId for a transaction inside $TicketId
$TransactionId = 117;

// AttachmentId for demo ticket (attachment content is text)
$AttachmentId  = 67;

// AttachmentId for demo ticket (attachment content is binary)
//$AttachmentId  = 68;



///////
// Regular cases
///////

$use_cases = [
    'create' => [
        'EmailAddress'  => $email,
        'Name'          => 'Joe Tester II',
        'Subject'       => 'Created from test suite ('.date("Y-m-d H:i:s").')',
        'Text'          => "This is a test\nfor creating a ticket"
    ],
    'reply' => [
        'TicketId'      => $TicketId,
        'Text'          => 'Correspondence added via REST. Emails are being sent',
        'EmailAddress'  => $email,
    ],
    'attachment' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
        'AttachmentId'  => $AttachmentId
    ],
    'attachment-content' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
        'AttachmentId'  => $AttachmentId
    ],
    'attachments' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
    ],
    'history' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
        'Format'        => 'l'
    ],
    'transaction' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
        'TransactionId' => $TransactionId,
    ],
    'edit' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
        'Subject'       => 'Subject changed via REST ('.date("Y-m-d H:i:s").')',
        'Priority'      => 13
    ],
    'comment' => [
        'TicketId'      => $TicketId,
        'Text'          => 'Comment added via REST. No emails are being sent',
        'EmailAddress'  => $email,
    ],
    'basics' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
    ],
    'show' => [
        'EmailAddress'  => $email,
        'TicketId'      => $TicketId,
    ],
    'search' => [
        'EmailAddress'  => $email,
        'query' => 'Queue="General"'
    ],
];


///////
// User management Tests
///////


// Rest manager retrieves user info
$retrieve_user = [
    'user' => [
        'EmailAddress'  => $email
    ]
];

// Attempt to create a ticket by a non existing user
// The user should be created in RT as part of the process
$nonexisting_user = [
    'create' => [
        'EmailAddress'  => 'doesnotexist5@localhost',
        'Subject'       => 'User created on the same ticket request'
    ],
];


//foreach ($retrieve_user as $k => $v) {
foreach ($use_cases as $k => $v) {
//foreach ($nonexisting_user as $k => $v) {
    system('clear');

    echo "action: $k\n";

    $result = (new PhpRtBridge($k, $v))->get_response();

    print_r($result);

    echo "Type 'y' to continue ... 'q' to exit: \n\n";
    $input = fgets(STDIN);
    if (strtolower(trim($input)) != 'y') {
        exit();
    }
}
