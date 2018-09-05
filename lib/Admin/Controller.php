<?php

namespace WHMCS\Module\Addon\My_Referral\Admin;

use WHMCS\Database\Capsule;
use WHMCS\Session;
use WHMCS\Admin;
use WHMCS\Carbon;

/**
 * Sample Admin Area Controller
 */
class Controller {

    /**
     * Index action.
     *
     * @param array $vars Module configuration parameters
     *
     * @return string
     */
    public function index($vars)
    {
		$whmcs = \App::self();
        // Get common module parameters
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $version = $vars['version']; // eg. 1.0
        $LANG = $vars['_lang']; // an array of the currently loaded language variables
		$systemurl = $whmcs->getSystemURL();

		$admin_id = Admin::getAdminID();
		require ROOTDIR . '/includes/clientfunctions.php';


		//Reviews of the Currently Logged in Reviewer
		$underreview = Capsule::table('review_responses')
						->join('tbltickets', 'tbltickets.id', '=', 'review_responses.tid')
						->join('tblticketreplies', 'tblticketreplies.id', '=', 'review_responses.ticket_replies_id')
						//->where('tblticketreplies.tid', 'tbltickets.id')
						//->where('mod_servermonitoring_services.uid', $userid)
						//->where('review_responses.admin_id', '!=', 0)
						->where('review_responses.admin_id', $admin_id)
						//->where('review_responses.reviewer_id', '!=', 0)
						->select('tbltickets.tid as tid' , 'tbltickets.title as title' , 'tblticketreplies.message as message' ,
								 'review_responses.id as id' , 'review_responses.admin_id as admin_id' ,
								 'review_responses.tid as ticket_id' , 'review_responses.ticket_replies_id as ticket_response_id' , 
								 'review_responses.status as status' , 'review_responses.userid as userid' ,
								 'review_responses.reviewer_id as reviewer_id')
						->get();


		
		$status_query = Capsule::table('review_responses_ticket_status_request')
								->join('tbltickets', 'tbltickets.id', '=', 'review_responses_ticket_status_request.ticketid')
								->where('review_responses_ticket_status_request.adminid' , $admin_id)
								->where('review_responses_ticket_status_request.review_responses_id' , 0)								
								//->where('review_responses_ticket_status_request.approved' , 0)
								->select('tbltickets.id as ticket_id' , 'tbltickets.tid as tid' , 'tbltickets.title as title' , 'tbltickets.userid as userid' ,
										 'review_responses_ticket_status_request.id as id',
										 'review_responses_ticket_status_request.adminid as adminid',
										 'review_responses_ticket_status_request.status as status',
										 'review_responses_ticket_status_request.ticketid as ticketid',
										 'review_responses_ticket_status_request.approved as approved'
										 )
								//->orderBy('review_responses_ticket_status_request.id' , 'desc')		 
								->get();



    	include('listing.php');

    }

    /**
     * Show action.
     *
     * @param array $vars Module configuration parameters
     *
     * @return string
     */
    public function view($vars) {
		
        // Get common module parameters
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $version = $vars['version']; // eg. 1.0
        $LANG = $vars['_lang']; // an array of the currently loaded language variables
		
		$today = Carbon::now()->format('Y-m-d H:i:s');
		
		if(isset($_GET['id']) && $_GET['id']):
		
			$id = $_GET['id'];
			$reviewer_id = Admin::getAdminID();
			
			//Now we get the query data
			$review = Capsule::table('review_responses')
									->join('tbltickets', 'tbltickets.id', '=', 'review_responses.tid')
									->join('tblticketreplies', 'tblticketreplies.id', '=', 'review_responses.ticket_replies_id')
									//->leftjoin('review_responses_replies', 'review_responses_replies.review_responses_id', '=', 'review_responses.id')
									->leftjoin('review_responses_ticket_status_request', 'review_responses_ticket_status_request.review_responses_id', '=', 'review_responses.id')
									->where('review_responses.id' , $id)
									->select('tbltickets.tid as tid' , 'tbltickets.title as title' , 'tblticketreplies.message as message' ,
											 'review_responses.id as id' , 'review_responses.admin_id as admin_id' ,
											 'review_responses.tid as ticket_id' , 'review_responses.ticket_replies_id as ticket_response_id' , 
											 'review_responses.status as status' , 'review_responses.notes as notes' , 'review_responses.userid as userid' ,
											 'review_responses.created_at as created_at' , 'review_responses.updated_at as updated_at' ,
											 'review_responses_ticket_status_request.status as taskstatuschange',
											 'review_responses_ticket_status_request.approved as approved',
											 'review_responses.reviewer_id as reviewer_id')
									->first();
			
			
			//Now Get the messages
			
			$messages = Capsule::table('review_responses_replies')
										->where('review_responses_replies.review_responses_id' , $review->id)
										->select('review_responses_replies.admin_id as admin_id', 
												 'review_responses_replies.reviewer_id as reviewer_id',
												 'review_responses_replies.message as message' , 
												 'review_responses_replies.msgstatus as msgstatus', 
												 'review_responses_replies.created_at as created_at',
												 'review_responses_replies.updated_at as updated_at')
										->get();
			

			//Noe The Comments are Read.
			Capsule::table('review_responses_replies')->where('reviewer_id' , $review->reviewer_id)->where('review_responses_id' , $review->id)->update(['msgstatus' => 1]);

			
			//echo 'WHY';
			include('view.php');

		endif;

		exit;
	}



	/**
     * Show submit.
     *
     * @param array $vars Module configuration parameters
     *
     * @return string
     */
	public function submit($vars) {
		
        // Get common module parameters
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $version = $vars['version']; // eg. 1.0
        $LANG = $vars['_lang']; // an array of the currently loaded language variables
		

		 if(isset($_POST['mode'])) {
		 
		 	$mode = $_POST['mode'];
			$today = Carbon::now()->format('Y-m-d H:i:s');
		 
		 	switch($mode) {
			
				case 'sendmessage':
					$id = $_POST['id'];
					Capsule::table('review_responses_replies')->insert(['review_responses_id' => $id ,'admin_id' => $_POST['admin_id'] , 'reviewer_id' => $_POST['reviewer_id'], 'message' => $_POST['message'], 'msgstatus' => 0 , 'created_at' => $today]);
					if($_POST['admin_id'] > 0) {
						header("Location: addonmodules.php?module=my_referral&action=view&id=".$id."");
						exit;
					}
					
					logActivity('Message: Sent to bloody Hell ', 0);
					
					header("Location: addonmodules.php?module=my_referral&action=review&id=".$id."");
					exit;
				
				break;
				
				default:
				break;
				
			}
		 
		 
		 }		
		

	}

	/**
     * Show submit.
     *
     * @param array $vars Module configuration parameters
     *
     * @return string
     */

	private function reviewStatus( $status ) {
		
		$LANG['unpublished'] = "Un-Published";
		$LANG['underreview'] = "Under-Review";
		$LANG['accepted'] = "Accepted";
		$LANG['rejected'] = "Rejected";
		
		$status_arr = [ 0 => $LANG['unpublished'] , 1 => $LANG['underreview'], 2 => $LANG['accepted'] , 3 => $LANG['rejected'] ];
		return $status_arr[$status];
	}

	private function reviewButton( $id ) {
		
		$admin_id = Admin::getAdminID();

		$reviews = Capsule::table('review_responses')->where('id' , $id)->first();
		
		
		$cnt = Capsule::table('review_responses_replies')->where('msgstatus' , 0)->where('reviewer_id' , $reviews->reviewer_id)->where('review_responses_id' , $reviews->id)->count();
		//review_responses_id
		$count_string = "";
		if($cnt > 0) {
			$count_string = "<i style=\"background: #e50000; border-radius: 1000px; display: inline-block; min-width: 20px;\" class=\"count\">" . $cnt . "</i>";	
		}
		
		if($reviews->admin_id == $admin_id) {
			return "<button onClick=\"window.open('addonmodules.php?module=my_referral&action=view&id=" . $reviews->id . "','viewwindow','width=1200,height=600,top=10,left=10,scrollbars=yes')\" class=\"btn btn-warning\">" . $count_string . " <i class=\"fa fa-comment\"></i> View & Comment</button>";			
		}
		
		return '';
		//<?php if($value->reviewer_id == 0 || $value->reviewer_id == $reviewer_id): <button onClick="window.open('addonmodules.php?module=review_responses&action=review&id=<?php echo $value->id; ','reviewwindow','width=800,height=500,top=100,left=100,scrollbars=no')" class="btn btn-info">Review</button><?php else: <button onClick="window.open('addonmodules.php?module=review_responses&action=view&id=<?php echo $value->id; ','viewwindow','width=800,height=500,top=100,left=100,scrollbars=no')" class="btn btn-warning">View</button><?php endif; 
		
	}


}
