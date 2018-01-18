<?php

/**
 * Rt Datasource
 *
 * @package     PhpRtBridge
 * @since       2012
 * @license     Mit license
 * @author      Hugo Escobar
 */

/**
 * PhpRtBridge
 *
 */

class PhpRtBridge
{
    /** RT server's full url */
    const HOST               = 'http://ticket/rt';
    const RTMGR_NAME         = 'rt_manager';
    const RTMGR_PASS         = 'r4e3w2q1';

    // write a random string of your own. Don't change it afterwards
    const PASSWORD_SALT      = 'bFGn99irg1hk2fggw45678jFSDBs0xd';

    /**
     * In some cases, administrators have set environment variables
     * with the sensitive information (usernames and passwords). In that
     * case, the value can be obtained later on via a 'getevnv' call or
     * a similar mechanism (SetEnv in apache, etc)
     *
     * @var array Default parameters. Custom values should be added
     * as part of every request
     */
    protected $params = [
        'Queue'              => 1, //'General'
        'Subject'            => '',
        'Priority'           => '',
        'Name'               => '',
        'Password'           => '',
        'Text'               => '',
        'EmailAddress'       => '',
        'TicketId'           => '',
        'AttachmentId'       => '',
        'HistoryId'          => '',
        'TransactionId'      => '',
        'Cc'                 => '',
        'Bcc'                => '',
        'Format'             => 's'
    ];

    /**
     * @var array http_verb
     */
    protected static $http_verb = [
        'GET'  => [
        ],
        'POST' => [
            'history', 'history-entry', 'transaction',
            'properties', 'attachment', 'attachments', 'attachment-content',
            'create', 'edit', 'reply', 'comment', 'user', 'search', 'basics',
            'create_user', 'edit_user', 'show'
        ]
    ];

    /**
     * @var array Translation from simple calls to related url fragment
     */
    protected static $rest_call = [
        'create'             => 'ticket/new',
        'basics'             => 'ticket/<TicketId>',
        'show'               => 'ticket/<TicketId>/show',
        'edit'               => 'ticket/<TicketId>/edit',
        'history'            => 'ticket/<TicketId>/history?format=<Format>',
        'transaction'        => 'ticket/<TicketId>/history/id/<TransactionId>',
        'links'              => 'ticket/<TicketId>/links/show',
        'attachments'        => 'ticket/<TicketId>/attachments',
        'attachment'         => 'ticket/<TicketId>/attachments/<AttachmentId>',
        'attachment-content' => 'ticket/<TicketId>/attachments/<AttachmentId>/content',
        'reply'              => 'ticket/<TicketId>/comment',
        'comment'            => 'ticket/<TicketId>/comment',
        'user'               => 'user/<EmailAddress>',
        'search'             => 'search/ticket?query=<query>&format=l',
        'create_user'        => 'user/new',
        'edit_user'          => 'user/<UserId>/edit'
    ];

    /** @var */
    protected $templates = [
        'create_user' => ['id: user/new', "Name", 'Privileged: 1',
        'EmailAddress', 'Password'],
        'edit_user' => ['id: user/edit', 'Name', 'RealName', 'Privileged: 1',
        'EmailAddress', 'Password', 'id: <UserId>'],
        'edit' => ['Subject', 'Priority'],
        'create' => ['id: ticket/new', 'Queue', 'Requestor', 'Subject', 'Text'],
        'user' => [], // empty tmeplate
        'basics' => [],
        'show' => [],
        'comment' => ['id: <TicketId>', 'Action: comment', 'Text'],
        'reply' => ['id: <TicketId>', 'Action: correspond', 'Text']
    ];

	/** @var string Description string for this Class. */
	public static $description = 'PhpRtBridge';

    /*  Regex for parsing cookies */
    const COOKIE_PATTERN     = "/^Set-Cookie: (.*?); path=.*?;.*?/ms";

    /** constant part of the url for Rest reqs */
    const REST_PREFIX        = '/REST/1.0/';

    /** @var $request_queue Web requests are always queued */
    protected $request_queue;

    /** @var string Queue the Rt Ticket belongs to.*/
    protected $user_fixed;

    /** @var string Stores 'initial' action for the duration of the orig req*/
    protected $action;

	/**
	 * This variable is a regex for those text responses indicating
	 * something is wrong with the request
	 */
	protected $bad_resp_rx =
        "/^\# (You are not allowed to display ticket (\d+)" .
        "|You are not allowed to modify ticket" .
        "|Ticket.*?does not exist" .
        "|Invalid object specification" .
        "|Could not create ticket" .
        "|Could not create user" .
        "|no user named (.*))/mi"
    ;

    /**
     * @var data structure for returning values
     */
    protected $response = [
        'message' => 'The request could not be processed',
        'success' => 0
    ];

    /**
     *  @return string
     */
    protected function generate_user_pwd()
    {
        return md5(
            $this->params['EmailAddress'].self::PASSWORD_SALT
       );
    }

    /**
     * @param string $action
     * @return mixed boolean|array
     */
    protected function prepare_requests(string $action)
    {
        $content = '';
        if (!empty($this->templates[$action])) {
            foreach($this->templates[$action] as $t) {
                if (strpos($t, ':') !== false) $content .= $t . "\n";
                if (!empty($this->params[$t])) {
                    $content .= $t . ': <' . $t . ">\n";
                }
            }
        }
        $req = ['url' => self::HOST . self::REST_PREFIX
            . PhpRtBridge::$rest_call[$action]];

        foreach ($this->params as $k => $v) {
            if (empty($v)) continue;

            if ($k == 'Text') $v = str_replace("\n", "\n ", $v);
            if ($k == 'Password') $v = $this->generate_user_pwd();
            $req['url'] = str_replace('<'.$k.'>', $v, $req['url']);
            if (!empty($content)) $content =
                str_replace('<'.$k.'>', $v, $content);
        }
        if (!empty($content)) $req['post']['content'] = $content;

        if (in_array($action, self::$http_verb['GET']))
            $req['http_verb']='GET';
        if (in_array($action, self::$http_verb['POST']))
            $req['http_verb']='POST';
        if (empty($req['http_verb'])) return false;

        $req['post']['user'] = $this->params['EmailAddress'];
        $req['post']['pass'] = $this->generate_user_pwd();

        if (preg_match('/user/i', $action)) {
            $req['post']['user'] = self::RTMGR_NAME;
            $req['post']['pass'] = self::RTMGR_PASS;
        }
        return $req;
    }

    /**
     * @param string $action
     * @return boolean
     */
    protected function send_requests(string $action)
    {
        if ($req = $this->prepare_requests($action)) {

            $resp = $this->request($req);
            if (empty($resp)) return false;

            return $resp;
        }
        return false;
    }

    /**
     * @return array
     */
    public function get_response()
    {
        return $this->response;
    }

	/**
	 * Default Constructor
	 *
     * @param string $action
	 * @param array $params request parameters.
	 */
	public function __construct(string $action, array $params)
	{
        $this->user_fixed   = 1;
        $this->action       = $action;

		if ($this->check_params($action, $params)) {
            if ($resp = $this->send_requests($action)) {
                $this->response = $resp;
            }
        }

        return $this;
	}

	/**
	 * Setup Configuration options
	 *
     * @param  array $params Configuration parameters provided by the user
	 * @return boolean
	 */
	protected function check_params(string $action, array $params)
    {
        foreach ($params as $k => $v) {
            $this->params[$k] = $v;
        }

        if (!empty($this->params['EmailAddress'])) {
            $this->params['Requestor'] = $this->params['EmailAddress'];
            $this->params['RealName']  = $this->params['Name'];
            if (empty($this->params['Name'])) {
                $this->params['Name']  = $this->params['EmailAddress'];
            }
        }

		return true;
	}

	/**
	 * @param array $params
	 */
	public function query(array $params)
	{
        $ticket  = [];
        $result = '';

  		switch($type){
        case 'TransactIds':
            preg_match_all("/^(\d+)\:\s/ms", $result, $transact_nums);
            rsort($transact_nums[1]);
            return $transact_nums[1];
            break;

        case 'TicketTransaction':
            $transaction = $this->extract_ticket_history(
                $this->parse_ticket_transaction($result),
                $params['TicketId']
           );

            // There are entries that may not be wanted in a ticket history
            // display, like an 'EmailRecord' ....
            $_is_valid_transaction =
                !empty($transaction['Type'])
                &&
                preg_match(
                    "/^(Correspond|Create|CustomField)/", $transaction['Type']
                )
                &&
                (
                	!preg_match("/^(\s|\t)*?$/",$transaction['Content'])
                		||
                	!empty($transaction['Attached_Files'])
               	)
            ;

            if ($transaction['Type'] == "CustomField") {
                if(!preg_match($regexp, $transaction['Description'])) {
                    return [];
                }
            }
            return $transaction;
            break;

        case 'AttachmentMetadata':
            $TicketId     = $params['TicketId'];
            $AttachmentId = $params['AttachmentId'];

            preg_match_all(
                "/(\d+)\:\s(.*?\.(" . $this->fileExtRegExp . "))\s/i", $result,
                $attachments
           );
            $attachment_index = null;
            for ($i = 0; $i < count($attachments[1]); $i++) {
                if ($AttachmentId == $attachments[1][$i]) {
                    $attachment_index = $i;
                    break;
                }
            }
            $file_name = (
				!empty($attachments[2][$attachment_index])
				?$attachments[2][$attachment_index]
				: ''
			);
            $file_ext  = (
				!empty($attachments[3][$attachment_index])
				?$attachments[3][$attachment_index]
				: ''
			);

            return [
                'file_name' => $file_name,
                'mime_type' => !empty($this->fileExt2Mime[$file_ext])
                    ? $this->fileExt2Mime[$file_ext]
                    : 'application/octet-stream',
                'file_ext'  => $file_ext
            ];
            break;

        case 'AttachmentContent':
            return preg_replace("/^RT.*?(\r?\n){1,2}/ms", "", $result);
            break;
        }
    }

	/**
	 * Handles web requests sent to other servers.
	 *
	 * @param array $p
	 *
	 * @return mixed boolean|array
	 */
	protected function request(array $p)
	{
		$buffer = '';
		$wc = (object) $p;

		// Are there any files in the submitted form?
		if (!empty($p['file'])) {
			$wc->file = $p['file'];
		}

		// Is basic auth required?
		if (!empty($p['basic_auth_user']) && !empty($p['basic_auth_pass'])) {
			$wc->basic_auth_user = $p['basic_auth_user'];
			$wc->basic_auth_pass = $p['basic_auth_pass'];
		}

		if (!empty($p['curl_opts'])) {
			$wc->curl_opts = $p['curl_opts'];
		}

		// Any curl error will be logged
		if (!($response = $this->_httpreq($wc))) {
			return false;
		}

		if (preg_match('/Credentials required/mis', $response)) {
            if ($this->user_fixed > 1) {
                trigger_error(
    				'Error: Something in RT is blocking user access.'
                    . "\n" . 'Please check permissions in RT',
                    E_USER_ERROR
    			);
    			return false;
            }

            // avoiding infinite loop ...
            $this->user_fixed++;

            // Deal with issue affecting the end-user
    		if (!$this->manage_user()) {
    			trigger_error(
    				'Error: could not run user management code.',
                    E_USER_ERROR
    			);
    			return false;
    		}
		}
		return $response;
	}

	/**
	 * Actual http client processing
	 *
	 * @param stdClass $wc array of parameters
	 *
	 * @return mixed
	 */
	private function _httpreq(stdClass $wc)
	{
		$ch = curl_init();

		// Follow redirections
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		if (!empty($wc->file)) {
			while(
                !mkdir(($tdir=sys_get_temp_dir().'/'.md5(rand(99,time()))),0700)
            ) {}
			for ($i = 1; $i <= count($wc->file); $i++) {
				if ($this->isUploadedFile($wc->file[$i])) {
					move_uploaded_file(
						$wc->file[$i]['tmp_name'],
						$tdir . '/' . $wc->file[$i]['name']
					);
                    $post_files['attachment_' . $i] = curl_file_create(
                        $tdir . '/' . $wc->file[$i]['name']
                   );
				}
			}
			if (!empty($post_files)) {
				$wc->post_data = array_merge($wc->post_data, $post_files);
			}
		}

		curl_setopt($ch, CURLOPT_URL, $wc->url);
		//if (empty($wc->nocookie)) {
			curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		//}
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		// Do we need to auth? (http basic auth)
		if (isset($wc->basic_auth_user) && isset($wc->basic_auth_pass)) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt (
				$ch,
				CURLOPT_USERPWD, $wc->basic_auth_user.':'.$wc->basic_auth_pass
			);
		}

		// if the http verb has been indicated:
		if (! empty($wc->http_verb) && strtoupper($wc->http_verb) == 'GET') {
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		} else {
			curl_setopt($ch, CURLOPT_POST, true);

            unset($wc->http_verb);

			// If we have data to be sent as a POST transaction,
			// the correct curl option is being set here
			if (!empty($wc->post)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $wc->post);
			}
		}

		// Last, if $wc carries any curl options, let's take them
		if (!empty($wc->curl_opts)) {
			foreach($wc->curl_opts as $opt_name => $opt_val) {
				eval("curl_setopt($" . "ch, $opt_name, $opt_val);");
			}
		}

		// Any curl error will be logged
		if (($buffer = curl_exec($ch)) === false) {
			trigger_error(
                'Error: ' .  curl_error($ch), E_USER_ERROR
            );
			return false;
        }

		curl_close($ch);

		if (!empty($tdir) && is_dir($tdir)) {
			$files = array_diff(scandir($tdir), ['.', '..']);
			foreach($files as $file) {
				unlink("$tdir/$file");
			}
			rmdir($tdir);
		}

		return $buffer;
	}

	/**
     * @return boolean
	 */
	protected function manage_user()
	{
        $user = $this->ticket_basics(
            $this->send_requests('user')
        );

        if (!empty($user) && !empty($user['__ret'])) {
            preg_match("/(\d+)$/", $user['id'], $m);
            $UserId = $m[1];

            if (!($resp = $this->reset_user($UserId))) {
                return false;
            }

        } else {
            // No user user info could be retrieved
            $resp = $this->create_user();
            if (empty($resp) || empty($resp['__ret'])) {
                return false;
            }
        }

        // re-submit the original request
        $req = $this->prepare_requests($this->action);
        if (!$resp = $this->request($req)) {
            return false;
        }

        return true;
	}

	/**
	 * @return array array
	 */
	protected function create_user()
	{
        $req = $this->prepare_requests('create_user');
        $raw = $this->request($req);
        $resp = $this->ticket_basics($raw);
		return $resp;
	}

    /**
     * @param  integer $UserId
	 * @return array
	 */
	protected function reset_user(string $UserId)
	{
        $this->params['UserId'] = $UserId;
        $req = $this->prepare_requests('edit_user');
        $raw = $this->request($req);
        $resp = $this->ticket_basics($raw);
		return $resp;
	}

    /**
     * REST response processing
     *
     * @param string $result text coming back from a REST request
     *
     * @return mixed
     */
    protected function ticket_basics($result)
    {
        $ticket['__ret'] = 1;
        if (preg_match($this->bad_resp_rx, $result, $m)) {
			$ticket['__ret'] = 0;
            $ticket['msg'] = $m[1];
			return $ticket;
        }
        $lines    = preg_split("/\n/", $result);

        // No real ticket will have less than 5 lines ...
        if (count($lines) < 5) return false;

        foreach ($lines as $line) {
            if (!strpos($line, ':')) continue;

            $split_result = preg_split('/\:\s/', $line);

            $k = (!empty($split_result[0])?$split_result[0]:'');
            $v = (!empty($split_result[1])?$split_result[1]:'');

            // Cleaning up custom field names
            $k = preg_replace("/^CF\.\{(.*?)\}$/", "$1", $k);

            $k = trim($k, " \:\n");
            $v = ltrim($v, " ");

            $ticket[$k] = $v;
        }
        return $ticket;
    }

    /**
     * Separates content in key->value pairs. It considers multiline values
     *
     * @param string $content Content from REST transaction
     *
     * @return array $res
     */
    private function parse_ticket_transaction($content)
    {
        $last_key = '';
        $res = $matches = [];

        $content_ar = preg_split("/\n/ims", $content);

	    $regexp = "/^(\S[^\:]+)\:(?:\s(.*?))?$/";
    	for($i = 0; $i < count($content_ar); $i++) {
            if (preg_match($regexp, $content_ar[$i], $matches)) {
	            $last_key = $matches[1];
                $res[$last_key] = (!empty($matches[2])?$matches[2]:'');
            } elseif (!empty($last_key)) {
           		$res[$last_key] .= "\n" . $content_ar[$i];
		    }
    	}
    	return $res;
    }

	/**
	 * Tries to match whatever comes in as <img src=<**this**>
	 * to an attached image
	 *
	 * @param array   $transaction
	 * @param integer $TicketId
	 *
	 * @return array
	 */
	protected function _fixImgTags(array $transaction, $TicketId)
	{
		if (!empty($transaction['Attached_Files']) && count($transaction['Attached_Files']) != 0) {
			$rx = "/<img.*?src\s?\=.*?(?P<sep>\W)(?P<files>[\_|\:|\@|\/|\\\|\w|\d|\.|s]+)(?P=sep)/ims";
			$html = $transaction['Content'];
			preg_match_all($rx, $html, $matches);
			if (count($matches['files'])) {
				$files = preg_replace("/^.*?([^\/|^\\\]+)$/", "$1",  $matches['files']);

				foreach ($files as $file) {
					$r = '"' . $file . '"';
					$attachment =
						Set::extract("/.[file_name=$file]", $transaction['Attached_Files']);

					if (empty($attachment) && preg_match("/^cid\:/", $file)) {
						$attachment = Set::extract(
							"/.[file_name=/(jpg|gif|png|tiff?)$/i]", $transaction['Attached_Files']
						);
					}

					if (!empty($attachment)) {
						$html = preg_replace(
							$r,
							"/rts/attachment_raw/tid:$TicketId/aid:" . $attachment[0]["file_id"],
							$html
						);
					}
				}
			}
			$transaction['Content'] = $html;
		}
		return $transaction;
	}

    /**
     * Returns ticket content stored as attachment by RT.
     *
     * @param type $TicketId
     * @param type $attch_id
     *
     * @return array
     */
    protected function _getHtmlTicketContent($TicketId, $attch_id)
    {
        // why 2? this is the third request for 'rts-ticket_transaction'
        $p = $this->rest['Attachment']['requests'][0];

        $p['url_prefix'] = str_replace('<TicketId>', $TicketId,
            $p['url_prefix']);
        $p['url_prefix'] = str_replace('<AttachmentId>', $attch_id,
            $p['url_prefix']);

        $result = $this->request($p);

        // I had to include 'text/plain' cases because in many cases, the
        // content has been stored as such even though the response contains
        // valid html formatting.
        if (preg_match("/^ContentType\: text\/(plain|html)/ms", $result)) {
            if (preg_match("/^Content\: (.*)/ms", $result, $matches)) {
                return $matches[1];
            }
        }
    }

	function isUploadedFile(&$params) {
		$val = $params;
		if (
            (isset($val['error']) && $val['error']==0)
                ||
            (!empty($val['tmp_name']) && $val['tmp_name'] != 'none')
        ) {
			return is_uploaded_file($val ['tmp_name']);
		}
		return false;
	}
}
