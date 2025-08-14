<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Fix_User_Activity_Etl_Nullable extends CI_Migration {

	public function up()
	{
		// This migration is intentionally empty - no changes needed
		// The nullable fields are already set correctly in the original table creation
	}

	public function down()
	{
		// No rollback needed for this migration
	}
}