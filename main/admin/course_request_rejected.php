<?php

/* For licensing terms, see /license.txt */

/**
 * A list containing the rejected course requests.
 *
 * @author José Manuel Abuin Mosquera <chema@cesga.es>, 2010
 * @author Bruno Rubio Gayo <brubio@cesga.es>, 2010
 * Centro de Supercomputacion de Galicia (CESGA)
 * @author Ivan Tcholakov <ivantcholakov@gmail.com> (technical adaptation for Chamilo 1.8.8), 2010
 */
$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

$this_section = SECTION_PLATFORM_ADMIN;

api_protect_admin_script();

// A check whether the course validation feature is enabled.
$course_validation_feature = api_get_setting('course_validation') == 'true';

// Filltering passed to this page parameters.
$accept_course_request = isset($_GET['accept_course_request']) ? intval($_GET['accept_course_request']) : '';
$delete_course_request = isset($_GET['delete_course_request']) ? intval($_GET['delete_course_request']) : '';
$request_info = isset($_GET['request_info']) ? intval($_GET['request_info']) : '';
$message = isset($_GET['message']) ? trim(Security::remove_XSS(stripslashes(urldecode($_GET['message'])))) : '';
$is_error_message = !empty($_GET['is_error_message']);

if ($course_validation_feature) {
    /**
     * Acceptance and creation of the requested course.
     */
    if (!empty($accept_course_request)) {
        $course_request_code = CourseRequestManager::get_course_request_code($accept_course_request);
        $course_id = CourseRequestManager::accept_course_request($accept_course_request);
        if ($course_id) {
            $course_code = CourseManager::get_course_code_from_course_id($course_id);
            $message = sprintf(get_lang('CourseRequestAccepted'), $course_request_code, $course_code);
            $is_error_message = false;
        } else {
            $message = sprintf(get_lang('CourseRequestAcceptanceFailed'), $course_request_code);
            $is_error_message = true;
        }
    } elseif (!empty($request_info)) {
        /**
         * Sending to the teacher a request for additional information about the proposed course.
         */
        $course_request_code = CourseRequestManager::get_course_request_code($request_info);
        $result = CourseRequestManager::ask_for_additional_info($request_info);
        if ($result) {
            $message = sprintf(get_lang('CourseRequestInfoAsked'), $course_request_code);
            $is_error_message = false;
        } else {
            $message = sprintf(get_lang('CourseRequestInfoFailed'), $course_request_code);
            $is_error_message = true;
        }
    } elseif (!empty($delete_course_request)) {
        /**
         * Deletion of a course request.
         */
        $course_request_code = CourseRequestManager::get_course_request_code($delete_course_request);
        $result = CourseRequestManager::delete_course_request($delete_course_request);
        if ($result) {
            $message = sprintf(get_lang('CourseRequestDeleted'), $course_request_code);
            $is_error_message = false;
        } else {
            $message = sprintf(get_lang('CourseRequestDeletionFailed'), $course_request_code);
            $is_error_message = true;
        }
    } elseif (isset($_POST['action'])) {
        /**
         * Form actions: delete.
         */
        switch ($_POST['action']) {
            // Delete selected courses
            case 'delete_course_requests':
                $course_requests = $_POST['course_request'];
                if (is_array($_POST['course_request']) && !empty($_POST['course_request'])) {
                    $success = true;
                    foreach ($_POST['course_request'] as $index => $course_request_id) {
                        $success &= CourseRequestManager::delete_course_request($course_request_id);
                    }
                    $message = $success ? get_lang('SelectedCourseRequestsDeleted') : get_lang('SomeCourseRequestsNotDeleted');
                    $is_error_message = !$success;
                }
                break;
        }
    }
} else {
    $link_to_setting = api_get_path(WEB_CODE_PATH).'admin/settings.php?category=Platform#course_validation';
    $message = sprintf(
        get_lang('PleaseActivateCourseValidationFeature'),
        sprintf('<strong><a href="%s">%s</a></strong>', $link_to_setting, get_lang('EnableCourseValidation'))
    );
    $is_error_message = true;
}

/**
 * Get the number of courses which will be displayed.
 */
function get_number_of_requests()
{
    return CourseRequestManager::count_course_requests(COURSE_REQUEST_REJECTED);
}

/**
 * Get course data to display.
 */
function get_request_data($from, $number_of_items, $column, $direction)
{
    $keyword = isset($_GET['keyword']) ? Database::escape_string(trim($_GET['keyword'])) : '';
    $course_request_table = Database::get_main_table(TABLE_MAIN_COURSE_REQUEST);

    $from = (int) $from;
    $number_of_items = (int) $number_of_items;
    $column = (int) $column;
    $direction = !in_array(strtolower(trim($direction)), ['asc', 'desc']) ? 'asc' : $direction;

    $sql = "SELECT
                id AS col0,
               code AS col1,
               title AS col2,
               category_code AS col3,
               tutor_name AS col4,
               request_date AS col5,
               id  AS col6
           FROM $course_request_table
           WHERE status = ".COURSE_REQUEST_REJECTED;

    if ($keyword != '') {
        $sql .= " AND (
            title LIKE '%".$keyword."%' OR
            code LIKE '%".$keyword."%' OR
            visual_code LIKE '%".$keyword."%'
        )";
    }
    $sql .= " ORDER BY col$column $direction ";
    $sql .= " LIMIT $from,$number_of_items";
    $res = Database::query($sql);

    $course_requests = [];
    while ($course_request = Database::fetch_row($res)) {
        $course_request[5] = api_get_local_time($course_request[5]);
        $course_requests[] = $course_request;
    }

    return $course_requests;
}

/**
 * Actions in the list: edit, accept, delete, request additional information.
 */
function modify_filter($id)
{
    $code = CourseRequestManager::get_course_request_code($id);
    $result = '<a href="course_request_edit.php?id='.$id.'&caller=2">'.
        Display::return_icon('edit.png', get_lang('Edit'), ['style' => 'vertical-align: middle;']).'</a>'.
        '&nbsp;<a href="?accept_course_request='.$id.'">'.
        Display::return_icon(
            'accept.png',
            get_lang('AcceptThisCourseRequest'),
            [
                'style' => 'vertical-align: middle;',
                'onclick' => 'javascript: if (!confirm(\''.addslashes(api_htmlentities(sprintf(get_lang('ANewCourseWillBeCreated'), $code), ENT_QUOTES)).'\')) return false;',
            ]
        ).
        '</a>';
    if (!CourseRequestManager::additional_info_asked($id)) {
        $result .= '&nbsp;<a href="?request_info='.$id.'">'.
            Display::return_icon(
                'request_info.gif',
                get_lang('AskAdditionalInfo'),
                [
                    'style' => 'vertical-align: middle;',
                    'onclick' => 'javascript: if (!confirm(\''.addslashes(api_htmlentities(sprintf(get_lang('AdditionalInfoWillBeAsked'), $code), ENT_QUOTES)).'\')) return false;',
                ]
            ).
            '</a>';
    }
    $result .= '&nbsp;<a href="?delete_course_request='.$id.'">'.
        Display::return_icon(
            'delete.png',
            get_lang('DeleteThisCourseRequest'),
            [
                'style' => 'vertical-align: middle;',
                'onclick' => 'javascript: if (!confirm(\''.addslashes(api_htmlentities(sprintf(get_lang('ACourseRequestWillBeDeleted'), $code), ENT_QUOTES)).'\')) return false;',
            ]
        ).
        '</a>';

    return $result;
}

$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('PlatformAdmin')];
$interbreadcrumb[] = ['url' => 'course_list.php', 'name' => get_lang('CourseList')];

$tool_name = get_lang('RejectedCourseRequests');

// Display confirmation or error message.
if (!empty($message)) {
    if ($is_error_message) {
        Display::addFlash(Display::return_message($message, 'error', false));
    } else {
        Display::addFlash(Display::return_message($message, 'normal', false));
    }
}

Display::display_header($tool_name);

if (!$course_validation_feature) {
    Display::display_footer();
    exit;
}

// Create a simple search-box.
$form = new FormValidator('search_simple', 'get', '', '', 'width=200px', false);
$renderer = $form->defaultRenderer();
$renderer->setCustomElementTemplate('<span>{element}</span> ');
$form->addElement('text', 'keyword', get_lang('Keyword'));
$form->addButtonSearch(get_lang('Search'));

// The action bar.
echo '<div style="float: right; margin-top: 5px; margin-right: 5px;">';
echo ' <a href="course_request_review.php">'.
    Display::return_icon('course_request_pending.png', get_lang('ReviewCourseRequests')).get_lang('ReviewCourseRequests').
    '</a>';
echo ' <a href="course_request_accepted.php">'.
    Display::return_icon('course_request_accepted.gif', get_lang('AcceptedCourseRequests')).get_lang('AcceptedCourseRequests').
    '</a>';
echo '</div>';
echo '<div class="actions">';
$form->display();
echo '</div>';

// Create a sortable table with the course data.
$table = new SortableTable('course_requests_rejected', 'get_number_of_requests', 'get_request_data', 5, 20, 'DESC');
$table->set_header(0, '', false);
$table->set_header(1, get_lang('Code'));
$table->set_header(2, get_lang('Title'));
$table->set_header(3, get_lang('Category'));
$table->set_header(4, get_lang('Teacher'));
$table->set_header(5, get_lang('CourseRequestDate'));
$table->set_header(6, '', false);
$table->set_column_filter(6, 'modify_filter');
$table->set_form_actions(['delete_course_requests' => get_lang('DeleteCourseRequests')], 'course_request');
$table->display();

Display::display_footer();
