<?php
	/*
	*
	*  API handle to create a new slide.
	*
	*  POST JSON parameters:
	*    * id      = The ID of the slide to modify or
	*                __API_K_NULL__ for new slide.
	*    * name    = The name of the slide.
	*    * index   = The index of the slide.
	*    * time    = The amount of time the slide is shown.
	*    * markup  = The markup of the slide.
	*
	*  Return value:
	*    A JSON encoded dictionary with the following keys:
	*     * id     = The ID of the created slide. **
	*     * name   = The name of the slide. **
	*     * index  = The index of the created slide. **
	*     * time   = The amount of time the slide is shown. **
	*     * error  = An error code or API_E_OK on success. ***
	*
	*   **  (Only exists if the call was successful.)
	*   *** (The error codes are listed in api_errors.php.)
	*/

	require_once($_SERVER['DOCUMENT_ROOT'].'/common/php/config.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/common/php/util.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/api/api.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/api/api_util.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/api/slide.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/api/api_errors.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/api/api_constants.php');

	$SLIDE_SAVE = new APIEndpoint(
		$method = API_METHOD['POST'],
		$response_type = API_RESPONSE['JSON'],
		$format = array(
			'id' => API_P_STR,
			'name' => API_P_STR,
			'index' => API_P_STR,
			'markup' => API_P_STR,
			'time' => API_P_INT
		)
	);
	api_endpoint_init($SLIDE_SAVE);

	if (!array_is_subset(SLIDE_REQ_KEYS,
		array_keys($SLIDE_SAVE->get()))) {

		// Required params do not exist. Return error.
		error_and_exit(API_E_INVALID_REQUEST);
	}

	$params_sanitized = array();
	$opt_index = array(
		'options' => array(
			'min_range' => 0
		)
	);

	// Only allow alphanumeric characters in the 'name'.
	$tmp = preg_replace('/[^a-zA-Z0-9_-]/', '',
				$SLIDE_SAVE->get('name'));
	if ($tmp === NULL) {
		error_and_exit(API_E_INTERNAL);
	}
	$params_sanitized['name'] = $tmp;

	// Make sure 'index' is an integer value.
	$tmp = filter_var($SLIDE_SAVE->get('index'), FILTER_VALIDATE_INT,
				$opt_index);
	if ($tmp === FALSE) {
		error_and_exit(API_E_INVALID_REQUEST);
	}
	$params_sanitized['index'] = $tmp;

	// Make sure 'time' is a float value in the correct range.
	$tmp = filter_var($SLIDE_SAVE->get('time'), FILTER_VALIDATE_FLOAT);
	if ($tmp === FALSE) {
		error_and_exit(API_E_INVALID_REQUEST);
	}
	$params_sanitized['time'] = $tmp;

	// TODO: Sanitize & process the markup!
	$params_sanitized['markup'] = $SLIDE_SAVE->get('markup');

	$slide = new Slide();

	/*
	*  If a slide ID is supplied *attempt* to use it.
	*  The $slide->set_data() function will do further checks
	*  on whether the ID is actually valid.
	*/
	$tmp = parse_api_constants($SLIDE_SAVE->get('id'));
	if ($tmp == API_CONST['API_K_NO_CONSTANT']) {
		$params_sanitized['id'] = $SLIDE_SAVE->get('id');
	} else if ($tmp != API_CONST['API_K_NULL']) {
		error_and_exit(API_E_INVALID_REQUEST);
	}

	if (!$slide->set_data($params_sanitized)) {
		/*
		*  Fails on missing parameters or if the
		*  provided ID doesn't exist.
		*/
		error_and_exit(API_E_INVALID_REQUEST);
	}

	try {
		$slide->write();
	} catch (Exception $e) {
		error_and_exit(API_E_INTERNAL);
	}

	juggle_slide_indices($slide->get('id'));

	$ret = $slide->get_data();
	$ret['error'] = API_E_OK;
	$ret_str = json_encode($ret);
	if ($ret_str === FALSE) {
		error_and_exit(API_E_INTERNAL);
	}
	echo $ret_str;
