<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class InitMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('email', Type::String)
			->addColumn('password', Type::String)
			->addColumn('name', Type::String)
			->addColumn('locale', Type::Enum, enum: ['en', 'cs'], default: 'en')
			->addColumn('current_workspace_id', Type::Int, size: 11, nullable: true)
			->addColumn('system_role', Type::Enum, enum: ['User', 'SystemAdmin'], default: 'User')
			->addColumn('email_verified', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['email'], 'users_email_unique', true)
			->create();

		$this->table('workspaces')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('owner_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['owner_id'], 'workspaces_owner_id_index', false)
			->addForeignKey('owner_id', 'users', 'id', 'workspaces_owner_id_users_id_fk')
			->create();

		$this->table('workspace_users')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('role', Type::Enum, enum: ['Owner', 'Admin', 'Member'])
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id', 'user_id'], 'workspace_users_unique', true)
			->addIndex(['user_id'], 'workspace_users_user_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'workspace_users_workspace_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'workspace_users_user_id_fk')
			->create();

		$this->table('invitations')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('inviter_id', Type::Int, size: 11)
			->addColumn('email', Type::String)
			->addColumn('token_hash', Type::String, size: 64)
			->addColumn('role', Type::Enum, enum: ['Owner', 'Admin', 'Member'])
			->addColumn('expires_at', Type::Timestamp)
			->addColumn('accepted_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['token_hash'], 'invitations_token_hash_unique', true)
			->addIndex(['workspace_id'], 'invitations_workspace_id_index', false)
			->addIndex(['email'], 'invitations_email_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'invitations_workspace_id_fk')
			->addForeignKey('inviter_id', 'users', 'id', 'invitations_inviter_id_fk')
			->create();

		$this->table('projects')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('description', Type::Text, nullable: true)
			->addColumn('prefix', Type::String, size: 16, default: '')
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'projects_workspace_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'projects_workspace_id_fk')
			->create();

		$this->table('workflows')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('project_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['project_id'], 'workflows_project_id_index', false)
			->addForeignKey('project_id', 'projects', 'id', 'workflows_project_id_projects_id_fk')
			->create();

		$this->table('statuses')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workflow_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('color', Type::String, size: 7)
			->addColumn('position', Type::Int)
			->addColumn('type', Type::Enum, enum: ['Start', 'Normal', 'Finish'])
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workflow_id'], 'statuses_workflow_id_index', false)
			->addForeignKey('workflow_id', 'workflows', 'id', 'statuses_workflow_id_workflows_id_fk')
			->create();

		$this->table('tasks')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('project_id', Type::Int, size: 11)
			->addColumn('status_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('description', Type::Text, nullable: true)
			->addColumn('priority', Type::Enum, enum: ['Low', 'Medium', 'High'], default: 'Medium')
			->addColumn('due_date', Type::Date, nullable: true)
			->addColumn('position', Type::Int)
			->addColumn('sequence_number', Type::Int, default: 0)
			->addColumn('created_by_agent', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['project_id'], 'tasks_project_id_index', false)
			->addIndex(['status_id'], 'tasks_status_id_index', false)
			->addIndex(['created_by_agent'], 'tasks_created_by_agent_index', false)
			->addForeignKey('project_id', 'projects', 'id', 'tasks_project_id_projects_id_fk')
			->addForeignKey('status_id', 'statuses', 'id', 'tasks_status_id_statuses_id_fk')
			->create();

		$this->table('events')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('author_id', Type::Int, size: 11, nullable: true)
			->addColumn('project_id', Type::Int, size: 11, nullable: true)
			->addColumn('workspace_id', Type::Int, size: 11, nullable: true)
			->addColumn('task_id', Type::Int, size: 11, nullable: true)
			->addColumn(
				'type',
				Type::Enum,
				enum: [
					'ProjectCreated',
					'ProjectUpdated',
					'ProjectDeleted',
					'WorkflowUpdated',
					'StatusCreated',
					'StatusUpdated',
					'StatusDeleted',
					'StatusMoved',
					'TaskCreated',
					'TaskUpdated',
					'TaskDeleted',
					'TaskMoved',
					'MemberRoleChanged',
					'OwnershipTransferred',
					'AdminDeletedWorkspace',
					'AdminDeletedUser',
					'AdminChangedSystemRole',
					'FieldCreated',
					'FieldUpdated',
					'FieldDeleted',
					'ProjectFieldsUpdated',
					'TaskFileAdded',
					'TaskFileDeleted',
					'TaskRelationCreated',
					'TaskRelationDeleted',
					'TagCreated',
					'TagUpdated',
					'TagDeleted',
					'TaskTagsUpdated',
					'TaskCommentAdded',
					'TaskCommentDeleted',
					'UserSelfDeleted',
				],
			)
			->addColumn('metadata', Type::Text)
			->addColumn('actor_type', Type::Enum, enum: ['Human', 'Agent'], default: 'Human')
			->addColumn('mcp_client_id', Type::String, size: 128, nullable: true)
			->addColumn('mcp_client_name', Type::String, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['project_id'], 'events_project_id_index', false)
			->addIndex(['workspace_id'], 'events_workspace_id_index', false)
			->addIndex(['author_id'], 'events_author_id_index', false)
			->addIndex(['actor_type'], 'events_actor_type_index', false)
			->addIndex(['mcp_client_id'], 'events_mcp_client_id_index', false)
			->addForeignKey('project_id', 'projects', 'id', 'events_project_id_projects_id_fk')
			->addForeignKey('workspace_id', 'workspaces', 'id', 'events_workspace_id_workspaces_id_fk')
			->create();

		$pdo = $this->databaseProvider->getDatabase()->getPdo();
		$pdo->exec(
			'ALTER TABLE events ADD CONSTRAINT events_author_id_users_id_fk '
			. 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
		);

		$this->table('oauth_clients')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('client_id', Type::String, size: 128)
			->addColumn('client_name', Type::String)
			->addColumn('redirect_uris', Type::String)
			->addColumn('user_id', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addForeignKey('user_id', 'users', 'id', 'oauth_clients_user_id_users_id_fk')
			->addIndex(['client_id'], 'oauth_clients_client_id_unique', true)
			->create();

		$this->table('oauth_authorizations')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('client_id', Type::String, size: 128)
			->addColumn('user_id', Type::Int)
			->addColumn('authorization_code_hash', Type::String, size: 64, nullable: true)
			->addColumn('code_challenge', Type::String, nullable: true)
			->addColumn('code_challenge_method', Type::String, size: 10, nullable: true)
			->addColumn('redirect_uri', Type::String, nullable: true)
			->addColumn('access_token_hash', Type::String, size: 64, nullable: true)
			->addColumn('refresh_token_hash', Type::String, size: 64, nullable: true)
			->addColumn('access_token_expires', Type::Int, nullable: true)
			->addColumn('refresh_token_expires', Type::Int, nullable: true)
			->addColumn('code_expires', Type::Int, nullable: true)
			->addColumn('revoked', Type::Boolean)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addForeignKey('user_id', 'users', 'id', 'oauth_authorizations_user_id_users_id_fk')
			->addIndex(['authorization_code_hash'], 'oauth_auth_code_hash_idx', false)
			->addIndex(['access_token_hash'], 'oauth_access_token_hash_idx', false)
			->addIndex(['refresh_token_hash'], 'oauth_refresh_token_hash_idx', false)
			->create();

		$this->table('fields')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('type', Type::Enum, enum: ['Text', 'Textarea', 'Select', 'Version'])
			->addColumn('required', Type::Boolean)
			->addColumn('default_value', Type::Text, nullable: true)
			->addColumn('options', Type::Text, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'fields_workspace_id_index', false)
			->addIndex(['workspace_id', 'name'], 'fields_workspace_name_unique', true)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'fields_workspace_id_fk')
			->create();

		$this->table('project_fields')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('project_id', Type::Int, size: 11)
			->addColumn('field_id', Type::Int, size: 11)
			->addColumn('position', Type::Int)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['project_id'], 'project_fields_project_id_index', false)
			->addIndex(['project_id', 'field_id'], 'project_fields_unique', true)
			->addForeignKey('project_id', 'projects', 'id', 'project_fields_project_id_fk')
			->addForeignKey('field_id', 'fields', 'id', 'project_fields_field_id_fk')
			->create();

		$this->table('task_field_values')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('field_id', Type::Int, size: 11)
			->addColumn('value', Type::Text, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id'], 'task_field_values_task_id_index', false)
			->addIndex(['field_id'], 'task_field_values_field_id_index', false)
			->addIndex(['task_id', 'field_id'], 'task_field_values_unique', true)
			->addForeignKey('task_id', 'tasks', 'id', 'task_field_values_task_id_fk')
			->addForeignKey('field_id', 'fields', 'id', 'task_field_values_field_id_fk')
			->create();

		$this->table('task_files')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('filename', Type::String)
			->addColumn('mime_type', Type::String)
			->addColumn('size', Type::Int)
			->addColumn('storage_key', Type::String, size: 512)
			->addColumn('uploaded_by_user_id', Type::Int, size: 11, nullable: true)
			->addColumn('uploaded_by_agent', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id'], 'task_files_task_id_index', false)
			->addForeignKey('task_id', 'tasks', 'id', 'task_files_task_id_fk')
			->addForeignKey('uploaded_by_user_id', 'users', 'id', 'task_files_uploaded_by_user_id_fk')
			->create();

		$this->table('task_relations')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('source_task_id', Type::Int, size: 11)
			->addColumn('target_task_id', Type::Int, size: 11)
			->addColumn('type', Type::Enum, enum: ['Related', 'Duplicates', 'Parent', 'DependsOn'])
			->addColumn('created_by_id', Type::Int, size: 11, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['source_task_id', 'target_task_id', 'type'], 'task_relations_unique', true)
			->addIndex(['target_task_id'], 'task_relations_target_id_index', false)
			->addForeignKey('source_task_id', 'tasks', 'id', 'task_relations_source_task_id_fk')
			->addForeignKey('target_task_id', 'tasks', 'id', 'task_relations_target_task_id_fk')
			->addForeignKey('created_by_id', 'users', 'id', 'task_relations_created_by_id_fk')
			->create();

		$this->table('tags')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('color', Type::String, size: 7)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'tags_workspace_id_index', false)
			->addIndex(['workspace_id', 'name'], 'tags_workspace_name_unique', true)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'tags_workspace_id_fk')
			->create();

		$this->table('task_tags')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('tag_id', Type::Int, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id'], 'task_tags_task_id_index', false)
			->addIndex(['tag_id'], 'task_tags_tag_id_index', false)
			->addIndex(['task_id', 'tag_id'], 'task_tags_unique', true)
			->addForeignKey('task_id', 'tasks', 'id', 'task_tags_task_id_fk')
			->addForeignKey('tag_id', 'tags', 'id', 'task_tags_tag_id_fk')
			->create();

		$this->table('password_reset_tokens')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('token_hash', Type::String, size: 64)
			->addColumn('expires_at', Type::Timestamp)
			->addColumn('used_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['user_id'], 'password_reset_tokens_user_id_index', false)
			->addIndex(['token_hash'], 'password_reset_tokens_token_hash_unique', true)
			->addForeignKey('user_id', 'users', 'id', 'password_reset_tokens_user_id_fk')
			->create();

		$this->table('email_verification_tokens')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('token_hash', Type::String, size: 64)
			->addColumn('expires_at', Type::Timestamp)
			->addColumn('used_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['user_id'], 'email_verification_tokens_user_id_index', false)
			->addIndex(['token_hash'], 'email_verification_tokens_token_hash_unique', true)
			->addForeignKey('user_id', 'users', 'id', 'email_verification_tokens_user_id_fk')
			->create();

		$this->table('task_comments')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('author_id', Type::Int, size: 11)
			->addColumn('body', Type::Text)
			->addColumn('actor_type', Type::Enum, enum: ['Human', 'Agent'], default: 'Human')
			->addColumn('mcp_client_id', Type::String, size: 128, nullable: true)
			->addColumn('mcp_client_name', Type::String, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id'], 'task_comments_task_id_index', false)
			->addIndex(['author_id'], 'task_comments_author_id_index', false)
			->addForeignKey('task_id', 'tasks', 'id', 'task_comments_task_id_fk')
			->addForeignKey('author_id', 'users', 'id', 'task_comments_author_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('task_comments')->drop();
		$this->table('email_verification_tokens')->drop();
		$this->table('password_reset_tokens')->drop();
		$this->table('task_tags')->drop();
		$this->table('tags')->drop();
		$this->table('task_relations')->drop();
		$this->table('task_files')->drop();
		$this->table('task_field_values')->drop();
		$this->table('project_fields')->drop();
		$this->table('fields')->drop();
		$this->table('oauth_authorizations')->drop();
		$this->table('oauth_clients')->drop();
		$this->table('events')->drop();
		$this->table('tasks')->drop();
		$this->table('statuses')->drop();
		$this->table('workflows')->drop();
		$this->table('projects')->drop();
		$this->table('invitations')->drop();
		$this->table('workspace_users')->drop();
		$this->table('workspaces')->drop();
		$this->table('users')->drop();
	}
}
