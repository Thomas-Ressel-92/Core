-- UP

ALTER TABLE exf_pwa_dataset
	ADD incremental_sync_via_attribute_alias varchar(50) NULL;
	
-- DOWN

ALTER TABLE exf_pwa_dataset
	DROP COLUMN incremental_sync_via_attribute_alias;