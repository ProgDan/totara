<?php

require_once('../../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/hierarchy/type/competency/lib.php');

// Page title
$pagetitle = 'assigncompetencytemplates';

///
/// Params
///

// Assign to id
$assignto = required_param('assignto', PARAM_INT);

// Framework id
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);


///
/// Permissions checks
///

// Setup page
admin_externalpage_setup('positionmanage');

// Check permissions
$sitecontext = get_context_instance(CONTEXT_SYSTEM);
require_capability('moodle/local:updateposition', $sitecontext);

// Setup hierarchy object
$hierarchy = new competency();

// Load framework
if (!$framework = $hierarchy->get_framework($frameworkid)) {
    error('Competency framework could not be found');
}

// Load competency templates to display
$items = $hierarchy->get_templates();

// Load currently assigned competency templates
// TODO

///
/// Display page
///

?>

<div class="selectcompetencies">

<h2><?php echo get_string($pagetitle, $hierarchy->prefix); ?></h2>

<div class="selected">
    <p>
        <?php echo get_string('dragheretoassign', $hierarchy->prefix) ?>
    </p>
</div>

<p>
    <?php echo get_string('locatecompetency', $hierarchy->prefix) ?>:
</p>

<ul class="treeview filetree">
<?php

if ($items) {

    foreach ($items as $item) {

        $li_class = '';
        $span_class = '';

        echo '<li class="'.$li_class.'" id="item_list_'.$item->id.'">';
        echo '<span id="item_'.$item->id.'" class="'.$span_class.'">'.format_string($item->fullname).'</span>';

        echo '</li>'.PHP_EOL;
    }
} else {
    echo '<li><span class="empty">'.get_string('nounassignedcompetencytemplates').'</span></li>'.PHP_EOL;
}

echo '</ul></div>';