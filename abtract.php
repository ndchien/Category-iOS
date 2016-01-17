<?php
if (! defined ( 'BASEPATH' )) exit ( 'No direct script access allowed' );

require_once(APPPATH.'libraries/Google/autoload.php');

class abtract extends CI_Controller {
	public $input_post;
	function __construct()
	{
		parent::__construct();	
		header('Content-type: text/json');
		$this->load->model("model_api_common","m_common");
		$this->input_post = json_decode ( file_get_contents ( 'php://input' ), true );
	}
	
	function index() {
		
	}
	
	private function get_youtube_list($app_id = 0,$search_key = '',$type = 'video'){
		$DEVELOPER_KEY = 'AIzaSyC89E2UzLeeyFJ_i2cByYi-UEaOCe7wftQ';
		$client = new Google_Client();
		$client->setDeveloperKey($DEVELOPER_KEY);
		$youtube = new Google_Service_YouTube($client);
		$this->getDataYoutube($youtube,$app_id,$search_key,$type);
	}
	
	private function getDataYoutube($youtube,$app_id,$search_key,$type,$nextPageToken = 1){
		try {
			if ($nextPageToken) { // our base case
				echo $nextPageToken;
				if ($nextPageToken == 1){
					$nextPageToken = "";
				}
				$searchResponse = $youtube->search->listSearch('id,snippet', array(
						'q' => $search_key,
						'maxResults' => '50',
						'type' => $type,
						'pageToken' => $nextPageToken
				));
				$nextPageToken = $searchResponse['nextPageToken'];
				foreach ($searchResponse['items'] as $searchResult) {
					switch ($searchResult['id']['kind']) {
						case 'youtube#video':
							if ($this->check_video_exists($searchResult['id']['videoId'])){
								break;
							}
							$video_array = array(
									"appId" => $app_id,
									"videoId" => $searchResult['id']['videoId'],
									"channelId" => $searchResult['snippet']['channelId'],
									"channelTitle" => $searchResult['snippet']['channelTitle'],
									"description" => $searchResult['snippet']['description'],
									"publishedAt" => date("Y-m-d H:i:s",strtotime($searchResult['snippet']['publishedAt'])) ,
									"title" => $searchResult['snippet']['title'],
									"thumbnails_default" => $searchResult['snippet']["thumbnails"]["default"]["url"],
									"thumbnails_medium" => $searchResult['snippet']["thumbnails"]["medium"]["url"],
									"thumbnails_high" => $searchResult['snippet']["thumbnails"]["high"]["url"],
							);
							$videosResponse = $youtube->videos->listVideos('snippet,contentDetails , recordingDetails , statistics , status', array(
									'id' => $searchResult['id']['videoId'],
							));
								
							// Display the list of matching videos.
							foreach ($videosResponse['items'] as $videoResult) {
								$video_array["largeDescription"] = $videoResult["snippet"]["description"];
								$video_array["viewCount"] = $videoResult["statistics"]["viewCount"];
								$video_array["likeCount"] = $videoResult["statistics"]["likeCount"];
								$video_array["dislikeCount"] = $videoResult["statistics"]["dislikeCount"];
								$video_array["favoriteCount"] = $videoResult["statistics"]["favoriteCount"];
								$video_array["commentCount"] = $videoResult["statistics"]["commentCount"];
								$video_array["duration"] = $videoResult["contentDetails"]["duration"];
								$video_array["dimension"] = $videoResult["contentDetails"]["dimension"];
								$video_array["definition"] = $videoResult["contentDetails"]["definition"];
								break;
							}
				
							$this->db->insert("xy_video",$video_array);
							break;
						case 'youtube#channel':
							if ($this->check_channel_exists($searchResult['id']['channelId'])){
								break;
							}
							$channel_array = array(
									"channelId" => $searchResult['id']['channelId'],
									"channelTitle" => $searchResult['snippet']['channelTitle'],
									"description" => $searchResult['snippet']['description'],
									"publishedAt" => date("Y-m-d H:i:s",strtotime($searchResult['snippet']['publishedAt'])) ,
									"title" => $searchResult['snippet']['title'],
									"thumbnails_default" => $searchResult['snippet']["thumbnails"]["default"]["url"],
									"thumbnails_medium" => $searchResult['snippet']["thumbnails"]["medium"]["url"],
									"thumbnails_high" => $searchResult['snippet']["thumbnails"]["high"]["url"],
							);
							$this->db->insert("xy_channel",$channel_array);
							break;
						case 'youtube#playlist':
							if ($this->check_playlist_exists($searchResult['id']['playlistId'])){
								break;
							}
							$playlist_array = array(
									"playlistId" => $searchResult['id']['playlistId'],
									"channelId" => $searchResult['snippet']['channelId'],
									"channelTitle" => $searchResult['snippet']['channelTitle'],
									"description" => $searchResult['snippet']['description'],
									"publishedAt" => date("Y-m-d H:i:s",strtotime($searchResult['snippet']['publishedAt'])) ,
									"title" => $searchResult['snippet']['title'],
									"thumbnails_default" => $searchResult['snippet']["thumbnails"]["default"]["url"],
									"thumbnails_medium" => $searchResult['snippet']["thumbnails"]["medium"]["url"],
									"thumbnails_high" => $searchResult['snippet']["thumbnails"]["high"]["url"],
							);
							$this->db->insert("xy_playlist",$playlist_array);
							break;
					}
				}
				return $this->getDataYoutube($youtube, $app_id, $search_key, $type, $nextPageToken);
			}
			else {
				return ; 
			}
		} catch (Google_Service_Exception $e) {
			// 			echo $e->getMessage();
		} catch (Google_Exception $e) {
			// 			echo $e->getMessage();
		}
	}
	
	function get_config(){
		$result = $this->db->get("xy_config")->result_array();
		echo $this->response_result(error_code_0,error_code_success,$result[0]);
	}
	
	function init_application(){
		$app_id = $this->input->post("appId");
		if (isset($this->input_post["appId"])) {
			$app_id = $this->input_post["appId"];
		}
		if (!$app_id){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		$check_exist_app = $this->db->where("appId",$app_id)
									->count_all_results("xy_app");
		if (!$check_exist_app){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		$app = $this->db->where("appId",$app_id)
						->get("xy_app")->result_array();
		$config = $this->db->get("xy_config")->result_array();
		$result = array(
				"app_info" => $app[0],
				"config_app_info" => $config[0]
		);
		echo $this->response_result(error_code_0,error_code_success,$result);
	}
	
	function getSequenceByAppId(){
		$app_id = $this->input->post("appId");
		if (isset($this->input_post["appId"])) {
			$app_id = $this->input_post["appId"];
		}
		if (!$app_id){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		$check_exist_app = $this->db->where("appId",$app_id)
									->count_all_results("xy_app");
		if (!$check_exist_app){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		$app = $this->db->where("appId",$app_id)
						->get("xy_app")->result_array();
		$this->getDataYoutubeInApp($app_id,$app[0]["name"]);
		echo $this->response_result(error_code_0,error_code_success,$app[0]);
	}
	
	private function getDataYoutubeInApp($appId = 0,$search_key = '') {
		$this->get_youtube_list($appId,$search_key);
	}
	
	function getListApp(){
		$app_id = $this->input->post("appId");
		if (isset($this->input_post["appId"])) {
			$app_id = $this->input_post["appId"];
		}
		if (!$app_id){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		$result = $this->db->where("appId !=" , $app_id)
							->get("xy_app")->result_array();
		echo $this->response_result(error_code_0,error_code_success,$result);
	} 
	
	function getHomeList(){
		$appId = $this->input->post("appId");
		$page_size = $this->input->post("page_size");
		$page_index = $this->input->post("page_index");
		
		if (isset($this->input_post["appId"])) {
			$appId = $this->input_post["appId"];
		}
		if (isset($this->input_post["page_size"])) {
			$page_size = $this->input_post["page_size"];
		}
		if (isset($this->input_post["page_index"])) {
			$page_index = $this->input_post["page_index"];
		}
		if (!$appId){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		$check_exist_app = $this->db->where("appId",$appId)
									->count_all_results("xy_app");
		if (!$check_exist_app){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		if (!$page_size){
			$page_size = default_fontend_pagesize;
		}
		if (!$page_index){
			$page_index = 1;
		}
		$array_top = $this->db->where("appId",$appId)
							->order_by("rand()","desc")
							->limit(default_fontend_pagesize)
							->get("xy_video")->result_array();
		$offset = ($page_index - 1) * $page_size;
		$array_bottom = $this->db->where("appId",$appId)
							->order_by("id","asc")
							->limit($page_size,$offset)
							->get("xy_video")->result_array();
		$result = array(
				"top"=>$array_top,
				"bottom"=>$array_bottom,
				"page_index" => $page_index,
				"page_size" => $page_size,
		);
		
		echo $this->response_result(error_code_0,error_code_success,$result);
	}
	
	function postPushNotificationInfo(){
		$appId = $this->input->post("appId");
		$isPro = $this->input->post("isPro");
		$deviceName = $this->input->post("deviceName");
		$deviceSystem = $this->input->post("deviceSystem");
		$deviceToken = $this->input->post("deviceToken");
		$deviceType = $this->input->post("deviceType");
		$deviceVersion = $this->input->post("deviceVersion");
		if (isset($this->input_post["appId"])) {
			$appId = $this->input_post["appId"];
		}
		if (isset($this->input_post["isPro"])) {
			$isPro = $this->input_post["isPro"];
		}
		if (isset($this->input_post["deviceName"])) {
			$deviceName = $this->input_post["deviceName"];
		}
		if (isset($this->input_post["deviceSystem"])) {
			$deviceSystem = $this->input_post["deviceSystem"];
		}
		if (isset($this->input_post["deviceToken"])) {
			$deviceToken = $this->input_post["deviceToken"];
		}
		if (isset($this->input_post["deviceType"])) {
			$deviceType = $this->input_post["deviceType"];
		}
		if (isset($this->input_post["deviceVersion"])) {
			$deviceVersion = $this->input_post["deviceVersion"];
		}
		if (!$deviceName){
			echo $this->response_result(error_code_1,"deviceName is required");
			return ;
		}
		if (!$deviceSystem){
			echo $this->response_result(error_code_1,"deviceSystem is required");
			return ;
		}
		if (!$deviceToken){
			echo $this->response_result(error_code_1,"deviceToken is required");
			return ;
		}
		if (!$deviceType){
			echo $this->response_result(error_code_1,"deviceType is required");
			return ;
		}
		if (!$deviceVersion){
			echo $this->response_result(error_code_1,"deviceVersion is required");
			return ;
		}
		if (!$appId){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		$check_exist_app = $this->db->where("appId",$appId)
									->count_all_results("xy_app");
		if (!$check_exist_app){
			echo $this->response_result(error_code_1,"App không tồn tại");
			return ;
		}
		
		$data = array(
				"deviceName" => $deviceName,
				"deviceSystem" => $deviceSystem,
				"deviceToken" => $deviceToken,
				"deviceType" => $deviceType,
				"deviceVersion" => $deviceVersion,
				"appId" => $appId,
				"isPro" => $isPro
		);
		
		$check_device = $this->db->where("deviceToken",$deviceToken)
								->where("appId",$appId)
									->count_all_results("xy_device");
		if ($check_device){
			$this->db->where("deviceToken",$deviceToken)
								->where("appId",$appId)
								->update("xy_device",$data);
		} else {
			$this->db->insert("xy_device",$data);
		}
		echo $this->response_result(error_code_0,error_code_success);
	} 
	
	function postCientSettings(){
		$deviceToken = $this->input->post("deviceToken");
		$isPush = $this->input->post("isPush");
		if (isset($this->input_post["deviceToken"])) {
			$deviceToken = $this->input_post["deviceToken"];
		}
		if (isset($this->input_post["isPush"])) {
			$isPush = $this->input_post["isPush"];
		}
		if (!$deviceToken){
			echo $this->response_result(error_code_1,"deviceToken is required");
			return ;
		}
		$check_device = $this->db->where("deviceToken",$deviceToken)
								->count_all_results("xy_device");
		if (!$check_device){
			echo $this->response_result(error_code_1,"deviceToken is required");
			return ;
		}
		$this->db->where("deviceToken",$deviceToken)
				->update('xy_device',array("isPush"=>$isPush));
		echo $this->response_result(error_code_0,error_code_success);
	} 
	
	private function check_video_exists($videoId){
		if (!$videoId){
			return;
		}
		return $this->db->where("videoId",$videoId)
					->count_all_results("xy_video");
	}
	
	private function check_channel_exists($channelId){
		if (!$channelId){
			return;
		}
		return $this->db->where("channelId",$channelId)
						->count_all_results("xy_channel");
	}
	
	private function check_playlist_exists($playlistId){
		if (!$playlistId){
			return;
		}
		return $this->db->where("playlistId",$playlistId)
						->count_all_results("xy_playlist");
	}
	
	protected function response_result($error = 0 , $message = '' , $response = array()){
		return json_encode(array(
				'error' => $error,
				'message' => $message,
				'response' => $response,
		));
	}
}

/* End of file home.php */
/* Location: ./application/controllers/home.php */