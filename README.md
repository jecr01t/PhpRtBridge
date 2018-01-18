PhpRtBridge
===========

##### Library for php integration to Request Tracker via REST

This software has been created with a particular need in mind: to allow php
developers to include RT's functionality in their applications with a minimum
effort (i.e. not having to write code or to deal with end user authentication
against RT)

##### Assumptions
- the end user will not interact with RT directly (via RT's Gui).
- the systems involved in the integration don't have a centralized
authentication system like Microsoft's Active Directory, LDAP, etc. If that's
the case, RT can interact with that kind of systems to know if the user must
be allowed to send requests.

##### Security considerations
In order to give the application the ability to allow end-users to interact
transparently with RT, a user in RT with the *'AdminUsers'* permission must be
created. This of course may have security implications. Just like many other
situations, the developer will have to balance flexibility versus security.
There are some measures that can be put in place in order to mitigate this
risk like using some kind of restriction for REST requests to RT by means of
firewall control, maybe in combination with RT's web server ACL, etc.

##### INSTALLATION

Download the file 'PhpRtBridge.php' and include it in your project.

##### USAGE

Inside the class file, three constants must be specified:

```
const HOST               = 'http://ticket/rt'; // RT's host
const RTMGR_NAME         = 'rt_manager';       // Rt's user mentioned above
const RTMGR_PASS         = '<a password>';
```

You may also want to set a value (random string) for the constant:
```
const PASWORD_SALT       = '';
```

The file demo.php can be used to test your setup.

The class constructor expects two parameters: (string) 'action' and
(array) $config. Following, an example:
```
$action = 'create';

$config = [
    'EmailAddress'  => $email,
    'Name'          => 'Joe Tester II',
    'Subject'       => 'Created from test suite ('.date("Y-m-d H:i:s").')',
    'Text'          => "This is a test\nfor creating a ticket"
];

$result = (new PhpRtBridge($action, $config))->get_response();
```

#### TODO:
- Improve the distribution of the sofware (via composer)
- Include the ability to set a timeout value
- Include samples of client (javascript/css/html) code to help implementing
the GUI side
- Add further processing for ticket returns to convert them to arrays ...

#### License
MIT
