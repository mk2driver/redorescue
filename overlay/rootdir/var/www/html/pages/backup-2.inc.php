<?php
// Load status
$status = get_status();

// Set drive name
if (isset($status->drive)) $_REQUEST['drive'] = $status->drive;
$status->drive = preg_replace('/[^A-Za-z0-9_\-]/', '', $_REQUEST['drive']);

// Save status
set_status($status);

// Load cached list of disks
$disks = get_disks();
foreach ($disks->blockdevices as $d) {
	if ($d->name==$status->drive) {
		//disk name match so save disk
		$disk = $d;
	}else{
		//disk name did not match so check for raid volume as child device, save child element as disk if matched
		if (property_exists($d, 'children')) { foreach ($d->children as $c) {if ($c->name == $status->drive) $disk = $c;} }
	}
}

// show error if disk or raid volume not found with the selected name
if (!isset($disk)) crash('Unable to read information for selected drive.', 'backup-1');
?>

<h1>Backup</h1>
<h3>Step 2: Select partition data to save</h3>
<p>Select which partition's data to include in the backup:</p>
<p>NOTE: The disk's partition table and boot record are always backed up so ALL partitions will be restored.  The options below set whether the file system and
data inside these partitions will be backed up.  If the disk contains non-standard partitions it's best to choose the 'Whole Disk Image' option as this will backup the
entire contents of the disk, regardless of partition structure or file systems in use.</p>

<form id="redo_form" class="form-horizontal">

  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th><input type="checkbox" id="toggle" onClick="$('input:checkbox').prop('checked', $(this).prop('checked'));"></th>
        <th>ID</th>
	<th>Size</th>
	<th>Type</th>
        <th>Filesystem</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
	    
<?php
$fsuse_var = 'fsuse%';

//list whole disk as an option for dd imaging
print "<tr>";
print "  <td><input type='checkbox' name='parts[]' id='$disk->name' value='$disk->name'></td>";
print "  <td>$disk->name</td>";
print "  <td>" . $disk->size . "B</td>";
print "  <td nowrap>Whole Disk Image</td>";
print "  <td nowrap>N/A</td>";
print "  <td>Block by block image of entire disk.</td>";
print "</tr>";

foreach ($disk->children as $p) {
	if ($p->parttype=='0x5' || $p->parttype==NULL) continue;
	$pct = '';
	if (property_exists($p, $fsuse_var)) $pct = $p->$fsuse_var;
	$notice = '';
	if (get_fs_tool($p->fstype)=='dd') $notice = ' <a data-toggle="tooltip" title="This filesystem requires imaging the entire partition, rather than simply the saved data on it."><i class="fas fa-info-circle text-info"></i></a>';
	if (substr($p->fstype,0,6)=='crypto') $notice .= ' <a data-toggle="tooltip" title="This partition is encrypted."><i class="fas fa-lock text-success"></i></a>';
	if ($p->fstype=='swap') {
		$notice = ' <a data-toggle="tooltip" title="In most cases it is not necessary to image a swap partition."><i class="fas fa-info-circle text-info"></i></a>';
		$checked = '';
	}
	if (isset($status->parts)) {
		// Restore the current setting
		$checked = '';
		if (in_array($p->name, $status->parts)) $checked = 'checked';
	} else {
		// Check most partitions by default
		if ($p->fstype!=='swap') $checked = 'checked';
	}
	$desc = array();
	if (!empty($p->label)) $desc[] = $p->label;
	if (!empty($p->os)) $desc[] = $p->os;
	$desc = trim(implode(' ', $desc));
	print "<tr".(empty($notice)?'':' class="info"').">";
	print "  <td><input type='checkbox' $checked name='parts[]' id='part_$p->name' value='$p->name'></td>";
	print "  <td>$p->name</td>";
	print "  <td>" . $p->size . "B</td>";
	print "  <td nowrap>$p->ptdesc</td>";
	print "  <td nowrap>" . strtoupper($p->fstype) . $notice . "</td>";
	print "  <td>$desc</td>";
	print "</tr>";
}
?>
    </tbody>
  </table>

  <div class="form-group">
    <div class="col-sm-12 text-right">
      <button type="reset" class="btn btn-default" onClick="$('#content').load('action.php?page=backup-1');">&lt; Back</button>
      <button type="submit" class="btn btn-warning">Next &gt;</button>
    </div>
  </div>

</form>

<script>
$("#redo_form").submit(function(event) {
	event.preventDefault();
	var url = 'action.php?page=backup-3';
	var formdata = $('#redo_form').serializeArray();
	// Include unchecked boxes
	formdata = formdata.concat(
		$('#redo_form input[type=checkbox]:not(:checked)').map(
			function() {
				return { 'name': this.name, 'value': false }
			}).get()
		);
	var posting = $.post(url, formdata);
	posting.done(function(data) {
		$("#content").html($(data));
	});
});
</script>
