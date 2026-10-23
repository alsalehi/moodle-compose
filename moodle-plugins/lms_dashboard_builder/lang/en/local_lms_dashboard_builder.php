<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for local_lms_dashboard_builder.
 *
 * @package    local_lms_dashboard_builder
 * @copyright  2026 TechHub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General & Navigation.
$string['pluginname'] = 'LMS Dashboard Builder';
$string['manage_dashboards'] = 'Manage Dashboards';
$string['manage_dashboards_desc'] = 'Create, configure, and publish reusable role-aware dashboards for Moodle blocks and course pages.';
$string['manage_queries'] = 'Custom Queries & Data Sources';
$string['manage_queries_sql'] = 'Manage Custom Queries & SQL';
$string['back_to_dashboards'] = 'Back to Dashboards';
$string['create_dashboard'] = 'Create Dashboard';
$string['edit_dashboard'] = 'Edit Dashboard';
$string['duplicate_dashboard'] = 'Duplicate Dashboard';
$string['delete_dashboard'] = 'Delete Dashboard';
$string['confirm_delete_dashboard'] = 'Are you sure you want to delete this dashboard?';
$string['confirm_delete_widget'] = 'Are you sure you want to delete this widget?';
$string['confirm_delete_query'] = 'Are you sure you want to delete this custom query?';
$string['dashboard_name'] = 'Dashboard Name';
$string['dashboard_name_placeholder'] = 'e.g. Department Course Dashboard';
$string['dashboard_description'] = 'Description';
$string['dashboard_description_placeholder'] = 'Short description of audience and purpose';
$string['dashboard_category'] = 'Category';
$string['category_tag'] = 'Category Tag';
$string['default_category'] = 'General';
$string['new_dashboard_default_name'] = 'New Dashboard';
$string['copy_suffix'] = ' (Copy)';
$string['status'] = 'Status';
$string['status_draft'] = 'Draft';
$string['status_published'] = 'Published';
$string['status_published_blocks'] = 'Published (Available in Blocks)';
$string['status_archived'] = 'Archived';
$string['actions'] = 'Actions';
$string['action'] = 'Action';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['preview'] = 'Preview';
$string['no_dashboards_found'] = 'No dashboards found.';
$string['no_dashboards_found_desc'] = 'Get started by creating your first reusable role-aware dashboard.';
$string['restore_starter_templates'] = 'Restore Starter Templates';
$string['build_edit'] = 'Build / Edit';
$string['toggle_publish_status'] = 'Toggle Published / Draft Status';
$string['export_dashboard_json'] = 'Export Dashboard JSON';

// Scopes & Role Targeting.
$string['dashboard_scope'] = 'Dashboard Scope';
$string['scope_and_category'] = 'Scope & Category';
$string['scope_course'] = 'Course Dashboard';
$string['scope_system'] = 'System / Site Dashboard';
$string['scope_category'] = 'Category Dashboard';
$string['scope_my'] = 'My Dashboard (Personal)';
$string['default_for_scope'] = 'Default for Scope';
$string['role_targeting'] = 'Role Targeting';
$string['role_based_visibility'] = 'Role-Based Visibility (Leave all unchecked for all roles)';
$string['all_permitted_roles'] = 'All Permitted Roles';
$string['dashboard_settings_heading'] = 'Dashboard Settings, Course Scope & Role-Based Access';
$string['save_dashboard_settings'] = 'Save Dashboard & Role Settings';
$string['widgets_and_filters'] = 'Widgets & Global Filters';
$string['widgets_count_badge'] = 'Widgets';
$string['filters_count_badge'] = 'Global Filters';

// Admin Settings.
$string['settings_cachettl'] = 'Widget Data Cache TTL (seconds)';
$string['settings_cachettl_desc'] = 'Time in seconds to cache widget query results in Moodle Universal Cache (MUC).';
$string['settings_maxrows'] = 'Maximum Query Rows';
$string['settings_maxrows_desc'] = 'Maximum number of rows returned per widget query execution.';
$string['settings_enablecustomsql'] = 'Enable Administrator Custom SQL';
$string['settings_enablecustomsql_desc'] = 'Allow users with local/lms_dashboard_builder:configure capability to author read-only SELECT queries.';

// Widgets & Builder.
$string['add_widget'] = 'Add Widget';
$string['add_custom_widget_heading'] = 'Add Custom Widget / Highly Customizable Chart';
$string['edit_custom_widget_heading'] = 'Edit Configured Widget';
$string['add_custom_widget_btn'] = 'Add Custom Widget to Dashboard';
$string['save_widget_changes'] = 'Save Widget Changes';
$string['cancel_edit'] = 'Cancel Edit';
$string['edit_widget'] = 'Edit Widget';
$string['delete_widget'] = 'Delete Widget';
$string['move_widget_up'] = 'Move Widget Up';
$string['move_widget_down'] = 'Move Widget Down';
$string['widget_order'] = 'Order';
$string['configured_widgets'] = 'Configured Dashboard Widgets';
$string['no_widgets_configured'] = 'No widgets configured on this dashboard yet. Add a preset or custom widget above.';
$string['quick_add_preset_heading'] = 'Quick Add Pre-Built Widget Preset';
$string['select_prebuilt_widget'] = 'Select Pre-Built Widget';
$string['insert_preset_widget'] = 'Insert Preset Widget';
$string['widget_title'] = 'Widget Title';
$string['widget_title_placeholder'] = 'e.g. Monthly Enrollment Trend';
$string['widget_type'] = 'Widget Type';
$string['query_source'] = 'Query Source';
$string['query_provider_label'] = 'Query Provider (Built-in or Custom Query)';
$string['grid_width'] = 'Grid Width (1-12)';
$string['grid_width_col'] = 'Grid Width';
$string['grid_col_3'] = '3 Columns (Quarter)';
$string['grid_col_4'] = '4 Columns (Third)';
$string['grid_col_6'] = '6 Columns (Half)';
$string['grid_col_8'] = '8 Columns (Two-Thirds)';
$string['grid_col_12'] = '12 Columns (Full Width)';
$string['chart_type_for_charts'] = 'Chart Type (for Charts)';
$string['color_palette'] = 'Color Palette';
$string['palette_modern'] = 'Modern Multi-Color';
$string['palette_ocean'] = 'Ocean Blue/Teal';
$string['palette_emerald'] = 'Emerald Green';
$string['palette_sunset'] = 'Sunset Warm';
$string['palette_monochrome'] = 'Slate Monochrome';
$string['custom_grouping_function'] = 'Custom Grouping Function';
$string['use_query_global_default'] = 'Use Query / Global Default';
$string['xaxis_label'] = 'X-Axis Label';
$string['xaxis_label_placeholder'] = 'Optional X-axis title';
$string['yaxis_label'] = 'Y-Axis Label';
$string['yaxis_label_placeholder'] = 'Optional Y-axis title';
$string['kpi_metric_key'] = 'KPI Metric Key';
$string['kpi_metric_placeholder'] = 'e.g. total_enrolled';
$string['value_suffix'] = 'Value Suffix';
$string['value_suffix_placeholder'] = 'e.g. %';
$string['legend_position'] = 'Legend Position';
$string['legend_bottom'] = 'Bottom';
$string['legend_top'] = 'Top';
$string['legend_right'] = 'Right';
$string['legend_none'] = 'Hidden';
$string['custom_hex_colors'] = 'Custom Hex Colors';
$string['custom_hex_colors_placeholder'] = '#3b82f6,#10b981';
$string['live_preview_heading'] = 'Live Interactive Dashboard Preview';
$string['block_placement_preview'] = 'Block Placement Preview';
$string['loading_widget'] = 'Loading widget...';

// Widget Types Catalog.
$string['widget_type_kpi'] = 'KPI Metric Card';
$string['widget_type_chart'] = 'Customizable Chart';
$string['widget_type_table'] = 'Sortable Data Table';
$string['widget_type_progress'] = 'Linear Progress Bar';
$string['widget_type_ring'] = 'Circular Progress Ring';
$string['widget_type_comparison'] = 'Metric Period Comparison Card';
$string['widget_type_activity_list'] = 'Upcoming Deadlines & Activity List';
$string['widget_type_status_distribution'] = 'Status Distribution Breakdown';
$string['widget_type_ranking'] = 'Top / Bottom Ranking Leaderboard';
$string['widget_type_text'] = 'Summary / Rich Text Banner';

// Chart Types Catalog.
$string['chart_type'] = 'Chart Type';
$string['chart_bar'] = 'Vertical Bar Chart';
$string['chart_horizontalBar'] = 'Horizontal Bar Chart';
$string['chart_stackedBar'] = 'Stacked Bar Chart';
$string['chart_line'] = 'Line Chart (Trend)';
$string['chart_area'] = 'Smooth Area Chart';
$string['chart_combo'] = 'Combo Bar + Line Chart';
$string['chart_doughnut'] = 'Doughnut Chart';
$string['chart_pie'] = 'Pie Chart';
$string['chart_gauge'] = 'Semi-Doughnut Gauge Chart';
$string['chart_radar'] = 'Radar / Spider Chart';
$string['chart_polarArea'] = 'Polar Area Chart';
$string['chart_scatter'] = 'Scatter Plot';

// Pre-Built Widget Presets.
$string['preset_course_enrolled_kpi'] = 'Active Enrolled Learners';
$string['preset_course_enrolled_kpi_sub'] = 'Enrolled in active scope';
$string['preset_course_completion_ring'] = 'Overall Course Completion Ring';
$string['preset_grading_backlog_kpi'] = 'Pending Grading Backlog';
$string['preset_grading_backlog_kpi_sub'] = 'Submissions awaiting review';
$string['preset_average_grade_kpi'] = 'Average Assessment Score';
$string['preset_average_grade_kpi_sub'] = 'Normalized percentage average';
$string['preset_enrollment_trend_area'] = 'Monthly Enrollment Trend';
$string['preset_top_courses_ranking'] = 'Top Courses by Enrollment';
$string['preset_grade_distribution_bar'] = 'Student Grade Band Distribution';
$string['preset_completion_status_dist'] = 'Learner Completion Status Breakdown';
$string['preset_upcoming_deadlines_list'] = 'Course Activities & Upcoming Deadlines';
$string['preset_engagement_by_module_chart'] = 'Activity Engagement by Module Type';
$string['preset_category_comparison_combo'] = 'Courses & Enrollments by Category';
$string['preset_enrollment_period_comparison'] = 'New vs Existing Enrollment Growth';
$string['axis_month'] = 'Month';
$string['axis_enrolled_learners'] = 'Enrolled Learners';
$string['axis_grade_band'] = 'Grade Band';
$string['axis_submissions'] = 'Submissions';
$string['axis_completions_events'] = 'Completions / Events';
$string['axis_module_type'] = 'Module Type';

// Global & Custom Filters.
$string['filters'] = 'Filters';
$string['global_filter_bar_heading'] = 'Per-Dashboard Customizable Global Filter Bar';
$string['filter_type'] = 'Filter Type';
$string['display_label'] = 'Display Label';
$string['display_label_placeholder'] = 'e.g. Course Category';
$string['default_value'] = 'Default Value';
$string['default_value_placeholder'] = 'e.g. 30d or 0';
$string['default_prefix'] = 'Default';
$string['custom_filter_key'] = 'Custom Filter Key (if Custom)';
$string['custom_filter_key_placeholder'] = 'e.g. department';
$string['custom_filter_options'] = 'Custom Filter Options (key:Label comma separated, if Custom)';
$string['custom_filter_options_placeholder'] = 'e.g. stem:STEM, arts:Arts & Humanities';
$string['add_global_filter_btn'] = 'Add Global Filter to Dashboard';
$string['remove_filter'] = 'Remove Filter';
$string['apply_filters'] = 'Apply Filters';
$string['reset_filters'] = 'Reset to Defaults';
$string['filter_daterange'] = 'Date Range';
$string['filter_date_from'] = 'From date';
$string['filter_date_to'] = 'To date';
$string['filter_course'] = 'Course';
$string['filter_category'] = 'Course Category';
$string['filter_status'] = 'Status';
$string['filter_type_daterange'] = 'Date Range (Relative Period)';
$string['filter_type_custom_dates'] = 'Custom Start & End Date Range';
$string['filter_type_categoryid'] = 'Course Category Selector';
$string['filter_type_courseid'] = 'Course Selector';
$string['filter_type_roleid'] = 'Moodle Role Selector';
$string['filter_type_cohortid'] = 'Cohort Selector';
$string['filter_type_groupid'] = 'Course Group Selector';
$string['filter_type_status'] = 'Completion / Enrollment Status';
$string['filter_type_modtype'] = 'Activity Module Type';
$string['filter_type_groupby'] = 'Dynamic Grouping Function Selector';
$string['filter_type_custom'] = 'Custom Parameter Filter (Select / Text / Number)';
$string['all'] = 'All';
$string['all_courses'] = 'All Courses';
$string['all_categories'] = 'All Categories';
$string['all_roles'] = 'All Roles';
$string['all_groups'] = 'All Groups';
$string['all_cohorts'] = 'All Cohorts';
$string['all_statuses'] = 'All Statuses';
$string['status_opt_active'] = 'Active / Completed';
$string['status_opt_inprogress'] = 'In Progress';
$string['status_opt_pending'] = 'Pending Review';
$string['status_opt_inactive'] = 'Suspended / Inactive';
$string['all_activities'] = 'All Activities';
$string['mod_assign'] = 'Assignments';
$string['mod_quiz'] = 'Quizzes';
$string['mod_forum'] = 'Forums';
$string['mod_scorm'] = 'SCORM Packages';
$string['mod_resource'] = 'Resources / Files';
$string['default_grouping'] = 'Default Grouping';
$string['period_last_7_days'] = 'Last 7 Days';
$string['period_last_30_days'] = 'Last 30 Days';
$string['period_last_90_days'] = 'Last 90 Days';
$string['period_last_quarter'] = 'Last Quarter (90d)';
$string['period_this_year'] = 'This Year';
$string['period_all_time'] = 'All Time';

// Grouping & Aggregation Engine.
$string['agg_count'] = 'Count';
$string['agg_count_distinct'] = 'Count Distinct';
$string['agg_sum'] = 'Sum';
$string['agg_avg'] = 'Average';
$string['agg_min'] = 'Minimum';
$string['agg_max'] = 'Maximum';
$string['agg_percentage_rate'] = 'Percentage Rate (%)';
$string['group_by_course'] = 'By Course';
$string['group_by_course_desc'] = 'Group records by course shortname or fullname.';
$string['group_by_category'] = 'By Course Category';
$string['group_by_category_desc'] = 'Group records by Moodle course category.';
$string['group_by_role'] = 'By Role';
$string['group_by_role_desc'] = 'Group records by assigned Moodle role.';
$string['group_by_cohort'] = 'By Cohort';
$string['group_by_cohort_desc'] = 'Group records by site or category cohort.';
$string['group_by_group'] = 'By Course Group';
$string['group_by_group_desc'] = 'Group records by course group membership.';
$string['group_by_activity_type'] = 'By Activity Module Type';
$string['group_by_activity_type_desc'] = 'Group records by activity module (assign, quiz, forum, etc.).';
$string['group_by_status'] = 'By Status';
$string['group_by_status_desc'] = 'Group records by completion, enrollment, or submission status.';
$string['group_time_day'] = 'By Day (YYYY-MM-DD)';
$string['group_time_day_desc'] = 'Bucket UNIX timestamps by calendar day.';
$string['group_time_week'] = 'By ISO Week (YYYY-Www)';
$string['group_time_week_desc'] = 'Bucket UNIX timestamps by ISO calendar week.';
$string['group_time_month'] = 'By Month (YYYY-MM)';
$string['group_time_month_desc'] = 'Bucket UNIX timestamps by calendar month.';
$string['group_time_quarter'] = 'By Quarter (YYYY-Q#)';
$string['group_time_quarter_desc'] = 'Bucket UNIX timestamps by calendar quarter (Q1-Q4).';
$string['group_time_year'] = 'By Year (YYYY)';
$string['group_time_year_desc'] = 'Bucket UNIX timestamps by calendar year.';
$string['group_by_grade_band'] = 'By Grade Band (Fail / Pass / Merit / Distinction)';
$string['group_by_grade_band_desc'] = 'Categorize percentage scores into academic grade bands.';
$string['group_by_progress_band'] = 'By Progress Band (0%, 1-49%, 50-99%, 100%)';
$string['group_by_progress_band_desc'] = 'Categorize learner progress percentages into completion bands.';
$string['group_custom_buckets'] = 'Custom Value / Range Buckets';
$string['group_custom_buckets_desc'] = 'Group values using custom threshold or label mappings configured on the widget or query.';

// Query Providers & Custom Queries Builder.
$string['provider_enrollment'] = 'Enrollment Analytics';
$string['provider_enrollment_desc'] = 'Enrolled learners, new enrollments, and enrollment distribution over time.';
$string['provider_completion'] = 'Course & Activity Completion';
$string['provider_completion_desc'] = 'Course and activity completion statistics, completion rates, and status distributions.';
$string['provider_assessment'] = 'Assessments & Grade Distribution';
$string['provider_assessment_desc'] = 'Assignment submissions, quiz attempts, grading backlog, and grade distributions.';
$string['provider_engagement'] = 'Learner Engagement & Activity';
$string['provider_engagement_desc'] = 'Active learners, course interaction events, and activity trends over time.';
$string['provider_course_stats'] = 'Course Overview Statistics';
$string['provider_course_stats_desc'] = 'Course dashboard statistics strictly scoped to the active course context.';
$string['provider_learner_progress'] = 'Learner Progress & Upcoming Deadlines';
$string['provider_learner_progress_desc'] = 'Learner progress band distributions, activity completion states, and upcoming deadlines.';
$string['provider_category_management'] = 'Category & Role Management Summary';
$string['provider_category_management_desc'] = 'Courses and enrollments grouped by course category and role distribution.';
$string['visual_query_builder_heading'] = 'Visual Query Builder (No SQL Required)';
$string['query_name'] = 'Query Name';
$string['query_name_placeholder'] = 'e.g. Monthly Enrollments by Method';
$string['base_data_source'] = 'Base Moodle Data Source';
$string['dimension_field'] = 'Dimension / X-Axis Field';
$string['metric_field'] = 'Metric Field';
$string['aggregation'] = 'Aggregation';
$string['row_limit'] = 'Row Limit';
$string['save_visual_query'] = 'Save Visual Query';
$string['custom_sql_builder_heading'] = 'Authorized Administrator Custom SQL Builder (Read-Only SELECT)';
$string['custom_sql_query_name'] = 'Custom SQL Query Name';
$string['custom_sql_query_name_placeholder'] = 'e.g. Custom Course Grade Averages';
$string['post_grouping_optional'] = 'Post-Grouping (Optional)';
$string['none_raw_sql_groups'] = 'None (Raw SQL Groups)';
$string['parameterized_readonly_sql'] = 'Parameterized Read-Only SQL (Supports :courseid, :categoryid, :starttime, :endtime, :userid, :status)';
$string['validate_save_sql_query'] = 'Validate & Save Custom SQL Query';
$string['custom_sql_capability_notice'] = 'Custom SQL authoring requires the local/lms_dashboard_builder:configure capability.';
$string['saved_custom_queries_heading'] = 'Saved Custom Queries (Visual & Custom SQL)';
$string['provider_key'] = 'Provider Key';
$string['query_type'] = 'Type';
$string['query_type_sql'] = 'Custom SQL';
$string['query_type_visual'] = 'Visual Query';
$string['definition_sql_summary'] = 'Definition / SQL Summary';
$string['no_custom_queries_saved'] = 'No custom queries saved yet. Use the Visual Query Builder or Custom SQL Builder above to create one.';
$string['builtin_providers_registry_heading'] = 'Pre-Built-In Query Providers Registry';
$string['provider_id'] = 'Provider ID';
$string['provider_name'] = 'Name';
$string['supported_widgets'] = 'Supported Widgets';
$string['supported_filters'] = 'Supported Filters';

// Access & Security.
$string['error_permission_denied'] = 'You do not have permission to view or manage this dashboard.';
$string['error_dashboard_not_found'] = 'The requested dashboard could not be found.';
$string['error_invalid_query'] = 'The requested query provider is invalid or unauthorized.';

// Capabilities.
$string['lms_dashboard_builder:manage'] = 'Manage and edit dashboards';
$string['lms_dashboard_builder:view'] = 'View dashboards';
$string['lms_dashboard_builder:configure'] = 'Configure dashboard data sources and settings';

// Privacy API.
$string['privacy:metadata'] = 'The LMS Dashboard Builder plugin stores dashboard configurations, saved queries, filter preferences, and audit logs.';
$string['privacy:metadata:dashboard'] = 'Records of dashboards created or modified by administrators.';
$string['privacy:metadata:dashboard:usermodified'] = 'The ID of the user who last modified the dashboard.';
$string['privacy:metadata:dashboard:timemodified'] = 'The timestamp when the dashboard was last modified.';
$string['privacy:metadata:query'] = 'Records of custom or visual queries saved in the dashboard builder.';
$string['privacy:metadata:query:usermodified'] = 'The ID of the user who last modified the query.';
$string['privacy:metadata:query:timemodified'] = 'The timestamp when the query was last modified.';
$string['privacy:metadata:audit'] = 'Security audit logs of dashboard mutations and administrative actions.';
$string['privacy:metadata:audit:userid'] = 'The ID of the user who performed the action.';
$string['privacy:metadata:audit:action'] = 'The name of the action performed.';
$string['privacy:metadata:audit:details'] = 'JSON-encoded details of the action.';
$string['privacy:metadata:audit:ipaddress'] = 'The IP address from which the action was performed.';
$string['privacy:metadata:audit:timecreated'] = 'The timestamp when the action occurred.';

// CSS Classes and Custom Styling.
$string['css_class'] = 'Custom CSS Classes';
$string['css_class_placeholder'] = 'e.g. shadow-lg border-primary rounded-4 my-custom-theme';

// Public Visitor Access & Enforcement.
$string['role_everyone_visitor'] = 'Everyone (even if not logged in / Public Visitors)';
$string['visitor_kpi_only_notice'] = 'Public visitor dashboards are restricted to pre-built KPI metric widgets to ensure visitor safety and data privacy.';

// Dashboard Filter Types (Request, Context, User).
$string['filter_type_from_request'] = 'From Request Parameter (GET/POST)';
$string['filter_type_from_context'] = 'From Page Context (Backend-Only)';
$string['filter_type_from_user'] = 'From Current User Profile (Backend-Only)';
$string['context_field'] = 'Context Field';
$string['context_field_courseid'] = 'Course ID';
$string['context_field_categoryid'] = 'Category ID';
$string['context_field_contextid'] = 'Context ID';
$string['context_field_contextlevel'] = 'Context Level';
$string['context_field_instanceid'] = 'Instance ID';
$string['user_field'] = 'User Field';
$string['user_field_userid'] = 'User ID';
$string['user_field_department'] = 'Department';
$string['user_field_institution'] = 'Institution';
$string['user_field_cohortid'] = 'Cohort ID';
$string['user_field_roleid'] = 'Active Role ID';
$string['request_param_name'] = 'Request Parameter Name';
$string['request_param_name_placeholder'] = 'e.g. dept or term';
$string['request_visibility_mode'] = 'Filter Visibility Mode';
$string['visibility_backend_only'] = 'Backend-Only (Hidden from user, derived from URL/Request)';
$string['visibility_visible_to_user'] = 'Interactive (Visible to user in filter bar with interactive controls)';

// Query Builder Backend-Only Filters.
$string['context_filter_label'] = 'Context Filter (Backend-Only)';
$string['backend_only_filter_hint'] = 'Evaluated securely on backend from current request context; cannot be manipulated by users.';
$string['user_filter_label'] = 'User Filter (Backend-Only)';
$string['context_filter_none'] = 'None (Global / No Context Restriction)';
$string['context_filter_courseid'] = 'Active Course Context (courseid)';
$string['context_filter_categoryid'] = 'Course Category Context (categoryid)';
$string['context_filter_contextid'] = 'Exact Context ID (contextid)';
$string['user_filter_none'] = 'None (All Users / No User Restriction)';
$string['user_filter_userid'] = 'Current Logged-in User (userid)';
$string['user_filter_department'] = 'User Department (department)';
$string['user_filter_institution'] = 'User Institution (institution)';
$string['user_filter_cohortid'] = 'User Cohort Membership (cohortid)';

// Public Visitor KPI Provider.
$string['provider_public_kpis'] = 'Public Site Overview KPIs';
$string['provider_public_kpis_desc'] = 'Aggregated, visitor-safe institution and platform metrics accessible to non-logged-in visitors.';
$string['kpi_total_courses'] = 'Total Available Courses';
$string['kpi_active_courses'] = 'Active Public Courses';
$string['kpi_total_students'] = 'Enrolled Learners';
$string['kpi_studying_students'] = 'Active Studying Learners';
$string['kpi_graduated_students'] = 'Completed / Graduated Learners';
$string['kpi_total_teachers'] = 'Instructors & Mentors';

// Public Visitor Pre-built KPI Presets.
$string['preset_public_total_courses'] = 'Total Available Courses';
$string['preset_public_total_courses_sub'] = 'Catalog of published offerings';
$string['preset_public_active_courses'] = 'Active Open Courses';
$string['preset_public_active_courses_sub'] = 'Courses actively in progress';
$string['preset_public_total_categories'] = 'Academic Categories';
$string['preset_public_total_categories_sub'] = 'Faculties and topic clusters';
$string['preset_public_total_students'] = 'Registered Learners';
$string['preset_public_total_students_sub'] = 'Total learner community';
$string['preset_public_studying_students'] = 'Actively Studying';
$string['preset_public_studying_students_sub'] = 'Learners with ongoing coursework';
$string['preset_public_graduated_students'] = 'Graduated Learners';
$string['preset_public_graduated_students_sub'] = 'Successfully completed curricula';
$string['preset_public_total_teachers'] = 'Expert Educators';
$string['preset_public_total_teachers_sub'] = 'Qualified instructors and coaches';
$string['preset_public_total_completions'] = 'Total Certifications & Completions';
$string['preset_public_total_completions_sub'] = 'Verified course achievements';
$string['preset_public_total_activities'] = 'Interactive Learning Modules';
$string['preset_public_total_activities_sub'] = 'Engaging tasks and assessments';
$string['preset_public_graduation_rate'] = 'Platform Completion Rate';
$string['preset_public_graduation_rate_sub'] = 'Overall course success percentage';

// Metric Keys Catalog.
$string['metric_total_courses'] = 'Total Courses';
$string['metric_active_courses'] = 'Active Courses';
$string['metric_total_categories'] = 'Total Categories';
$string['metric_total_students'] = 'Total Students';
$string['metric_studying_students'] = 'Studying Students';
$string['metric_graduated_students'] = 'Graduated Students';
$string['metric_total_teachers'] = 'Total Teachers';
$string['metric_total_completions'] = 'Total Completions';
$string['metric_total_activities'] = 'Total Activities';
$string['metric_graduation_rate'] = 'Graduation Rate (%)';
$string['metric_enrolled_students'] = 'Enrolled Students';
$string['metric_completion_rate'] = 'Completion Rate (%)';
$string['metric_active_students'] = 'Active Students';
$string['metric_completed_students'] = 'Completed Students';
$string['metric_total_submissions'] = 'Total Submissions';
$string['metric_pending_grading'] = 'Pending Grading';
$string['metric_average_grade'] = 'Average Grade';
$string['metric_pass_rate'] = 'Pass Rate (%)';
$string['metric_completed_count'] = 'Completed Count';
$string['metric_in_progress_count'] = 'In Progress Count';
$string['metric_total_enrolled'] = 'Total Enrolled';
$string['metric_new_enrollments'] = 'New Enrollments';
$string['metric_active_learners'] = 'Active Learners';
$string['metric_total_views'] = 'Total Views';
$string['metric_total_posts'] = 'Total Posts';
$string['metric_active_users'] = 'Active Users';

