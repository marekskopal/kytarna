<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;
use MarekSkopal\ORM\Migrations\Migration\Query\Enum\ReferenceOptionEnum;

final class InitialSchema extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('failed_login_attempts', Type::Int, default: 0)
			->addColumn('token_version', Type::Int, default: 0)
			->addColumn('locked_until', Type::Timestamp, nullable: true)
			->addColumn('onboarding_completed_at', Type::Timestamp, nullable: true)
			->addColumn('google_id', Type::String, nullable: true)
			->addColumn('default_saved_view_id', Type::Int, nullable: true)
			->addColumn('email', Type::String)
			->addColumn('password', Type::String, nullable: true)
			->addColumn('name', Type::String)
			->addColumn('locale', Type::Enum, enum: ['en', 'cs'], default: 'en')
			->addColumn('theme', Type::Enum, enum: ['system', 'light', 'dark'], default: 'system')
			->addColumn('current_workspace_id', Type::Int, nullable: true)
			->addColumn('system_role', Type::Enum, enum: ['User', 'SystemAdmin'], default: 'User')
			->addColumn('email_verified', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
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
			->addForeignKey('user_id', 'users', 'id', 'email_verification_tokens_user_id_users_id_fk')
			->create();

		$this->table('oauth_clients')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('client_id', Type::String, size: 128)
			->addColumn('client_name', Type::String)
			->addColumn('redirect_uris', Type::String)
			->addColumn('user_id', Type::Int, nullable: true, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['user_id'], 'oauth_clients_user_id_index', false)
			->addForeignKey('user_id', 'users', 'id', 'oauth_clients_user_id_users_id_fk')
			->create();

		$this->table('oauth_authorizations')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('client_id', Type::String, size: 128)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('authorization_code_hash', Type::String, nullable: true, size: 64)
			->addColumn('code_challenge', Type::String, nullable: true)
			->addColumn('code_challenge_method', Type::String, nullable: true, size: 10)
			->addColumn('redirect_uri', Type::String, nullable: true)
			->addColumn('access_token_hash', Type::String, nullable: true, size: 64)
			->addColumn('refresh_token_hash', Type::String, nullable: true, size: 64)
			->addColumn('access_token_expires', Type::Int, nullable: true)
			->addColumn('refresh_token_expires', Type::Int, nullable: true)
			->addColumn('code_expires', Type::Int, nullable: true)
			->addColumn('revoked', Type::Boolean)
			->addColumn('family_id', Type::String, nullable: true, size: 32)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['user_id'], 'oauth_authorizations_user_id_index', false)
			->addForeignKey('user_id', 'users', 'id', 'oauth_authorizations_user_id_users_id_fk')
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
			->addIndex(['workspace_id'], 'workspace_users_workspace_id_index', false)
			->addIndex(['user_id'], 'workspace_users_user_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'workspace_users_workspace_id_workspaces_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'workspace_users_user_id_users_id_fk')
			->create();

		$this->table('notifications')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('type', Type::Enum, enum: ['LectureMoved'])
			->addColumn('lecture_id', Type::Int, nullable: true)
			->addColumn('course_id', Type::Int, nullable: true)
			->addColumn('actor_id', Type::Int, nullable: true)
			->addColumn('actor_name', Type::String, nullable: true)
			->addColumn('data', Type::Text, nullable: true)
			->addColumn('read_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['user_id'], 'notifications_user_id_index', false)
			->addForeignKey('user_id', 'users', 'id', 'notifications_user_id_users_id_fk')
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
			->addIndex(['workspace_id'], 'invitations_workspace_id_index', false)
			->addIndex(['inviter_id'], 'invitations_inviter_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'invitations_workspace_id_workspaces_id_fk')
			->addForeignKey('inviter_id', 'users', 'id', 'invitations_inviter_id_users_id_fk')
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
			->addForeignKey('user_id', 'users', 'id', 'password_reset_tokens_user_id_users_id_fk')
			->create();

		$this->table('saved_views')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('filter_config', Type::Text)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'saved_views_workspace_id_index', false)
			->addIndex(['user_id'], 'saved_views_user_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'saved_views_workspace_id_workspaces_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'saved_views_user_id_users_id_fk')
			->create();

		$this->table('tags')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('color', Type::String, size: 7)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'tags_workspace_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'tags_workspace_id_workspaces_id_fk')
			->create();

		$this->table('courses')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('prefix', Type::String, size: 16)
			->addColumn('description', Type::Text, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'courses_workspace_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'courses_workspace_id_workspaces_id_fk')
			->create();

		$this->table('events')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('author_id', Type::Int, nullable: true, size: 11)
			->addColumn(
				'type',
				Type::Enum,
				enum: ['CourseCreated', 'CourseUpdated', 'CourseDeleted', 'LectureCreated', 'LectureUpdated', 'LectureDeleted', 'LectureMoved', 'LectureArchived', 'LectureUnarchived', 'LecturesBulkUpdated', 'SongCreated', 'SongUpdated', 'SongDeleted', 'SongMoved', 'SongArchived', 'SongUnarchived', 'SongAddedToCourse', 'SongRemovedFromCourse', 'SongFileAdded', 'SongFileDeleted', 'SongTagsUpdated', 'MemberRoleChanged', 'OwnershipTransferred', 'AdminDeletedWorkspace', 'AdminDeletedUser', 'AdminChangedSystemRole', 'LectureFileAdded', 'LectureFileDeleted', 'TagCreated', 'TagUpdated', 'TagDeleted', 'LectureTagsUpdated', 'UserSelfDeleted'],
			)
			->addColumn('metadata', Type::Text)
			->addColumn('course_id', Type::Int, nullable: true, size: 11)
			->addColumn('workspace_id', Type::Int, nullable: true, size: 11)
			->addColumn('lecture_id', Type::Int, nullable: true)
			->addColumn('actor_type', Type::Enum, enum: ['Human', 'Agent'], default: 'Human')
			->addColumn('mcp_client_id', Type::String, nullable: true, size: 128)
			->addColumn('mcp_client_name', Type::String, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['author_id'], 'events_author_id_index', false)
			->addIndex(['course_id'], 'events_course_id_index', false)
			->addForeignKey('author_id', 'users', 'id', 'events_author_id_users_id_fk', ReferenceOptionEnum::SetNull)
			->addForeignKey('course_id', 'courses', 'id', 'events_course_id_courses_id_fk')
			->create();

		$this->table('lectures')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('course_id', Type::Int, size: 11)
			->addColumn('status', Type::Enum, enum: ['ToLearn', 'Learning', 'Mastered'], default: 'ToLearn')
			->addColumn('name', Type::String)
			->addColumn('description', Type::Text, nullable: true)
			->addColumn('position', Type::Int)
			->addColumn('sequence_number', Type::Int)
			->addColumn('tuning', Type::String, nullable: true)
			->addColumn('capo', Type::Int, nullable: true)
			->addColumn('target_tempo_bpm', Type::Int, nullable: true)
			->addColumn('difficulty', Type::Enum, nullable: true, enum: ['Beginner', 'Intermediate', 'Advanced'])
			->addColumn('created_by_agent', Type::Boolean, default: false)
			->addColumn('archived_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['course_id'], 'lectures_course_id_index', false)
			->addForeignKey('course_id', 'courses', 'id', 'lectures_course_id_courses_id_fk')
			->create();

		$this->table('songs')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('course_id', Type::Int, nullable: true, size: 11)
			->addColumn('status', Type::Enum, enum: ['ToLearn', 'Learning', 'Mastered'], default: 'ToLearn')
			->addColumn('name', Type::String)
			->addColumn('position', Type::Int)
			->addColumn('sequence_number', Type::Int, nullable: true)
			->addColumn('description', Type::Text, nullable: true)
			->addColumn('tuning', Type::String, nullable: true)
			->addColumn('capo', Type::Int, nullable: true)
			->addColumn('target_tempo_bpm', Type::Int, nullable: true)
			->addColumn('difficulty', Type::Enum, nullable: true, enum: ['Beginner', 'Intermediate', 'Advanced'])
			->addColumn('author_name', Type::String, nullable: true)
			->addColumn('album_name', Type::String, nullable: true)
			->addColumn('cover_image_key', Type::String, nullable: true, size: 512)
			->addColumn('cover_image_mime_type', Type::String, nullable: true)
			->addColumn('created_by_agent', Type::Boolean, default: false)
			->addColumn('archived_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'songs_workspace_id_index', false)
			->addIndex(['course_id'], 'songs_course_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'songs_workspace_id_workspaces_id_fk')
			->addForeignKey('course_id', 'courses', 'id', 'songs_course_id_courses_id_fk')
			->create();

		$this->table('lecture_files')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('lecture_id', Type::Int, size: 11)
			->addColumn('filename', Type::String)
			->addColumn('mime_type', Type::String)
			->addColumn('size', Type::Int)
			->addColumn('storage_key', Type::String, size: 512)
			->addColumn('uploaded_by_user_id', Type::Int, nullable: true, size: 11)
			->addColumn('uploaded_by_agent', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['lecture_id'], 'lecture_files_lecture_id_index', false)
			->addIndex(['uploaded_by_user_id'], 'lecture_files_uploaded_by_user_id_index', false)
			->addForeignKey('lecture_id', 'lectures', 'id', 'lecture_files_lecture_id_lectures_id_fk')
			->addForeignKey(
				'uploaded_by_user_id',
				'users',
				'id',
				'lecture_files_uploaded_by_user_id_users_id_fk',
				ReferenceOptionEnum::SetNull,
			)
			->create();

		$this->table('lecture_links')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('lecture_id', Type::Int, size: 11)
			->addColumn('url', Type::String, size: 2048)
			->addColumn('kind', Type::Enum, enum: ['youtube', 'other'])
			->addColumn('label', Type::String, nullable: true)
			->addColumn('timestamp_seconds', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['lecture_id'], 'lecture_links_lecture_id_index', false)
			->addForeignKey('lecture_id', 'lectures', 'id', 'lecture_links_lecture_id_lectures_id_fk')
			->create();

		$this->table('tabs')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('lecture_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('alphatex_content', Type::Text)
			->addColumn('source_type', Type::Enum, enum: ['authored', 'imported_gp'])
			->addColumn('original_file_id', Type::Int, nullable: true, size: 11)
			->addColumn('tempo', Type::Int, nullable: true)
			->addColumn('tuning', Type::String, nullable: true)
			->addColumn('track_count', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['lecture_id'], 'tabs_lecture_id_index', false)
			->addIndex(['original_file_id'], 'tabs_original_file_id_index', false)
			->addForeignKey('lecture_id', 'lectures', 'id', 'tabs_lecture_id_lectures_id_fk')
			->addForeignKey(
				'original_file_id',
				'lecture_files',
				'id',
				'tabs_original_file_id_lecture_files_id_fk',
				ReferenceOptionEnum::SetNull,
			)
			->create();

		$this->table('lecture_tags')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('lecture_id', Type::Int, size: 11)
			->addColumn('tag_id', Type::Int, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['lecture_id'], 'lecture_tags_lecture_id_index', false)
			->addIndex(['tag_id'], 'lecture_tags_tag_id_index', false)
			->addForeignKey('lecture_id', 'lectures', 'id', 'lecture_tags_lecture_id_lectures_id_fk')
			->addForeignKey('tag_id', 'tags', 'id', 'lecture_tags_tag_id_tags_id_fk')
			->create();

		$this->table('progress_entries')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('lecture_id', Type::Int, size: 11)
			->addColumn('practiced_at', Type::Date)
			->addColumn('note', Type::Text, nullable: true)
			->addColumn('tempo_bpm', Type::Int, nullable: true)
			->addColumn('duration_minutes', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['lecture_id'], 'progress_entries_lecture_id_index', false)
			->addForeignKey('lecture_id', 'lectures', 'id', 'progress_entries_lecture_id_lectures_id_fk')
			->create();

		$this->table('lecture_watchers')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('lecture_id', Type::Int, size: 11)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['lecture_id'], 'lecture_watchers_lecture_id_index', false)
			->addIndex(['user_id'], 'lecture_watchers_user_id_index', false)
			->addForeignKey('lecture_id', 'lectures', 'id', 'lecture_watchers_lecture_id_lectures_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'lecture_watchers_user_id_users_id_fk')
			->create();

		$this->table('song_files')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('song_id', Type::Int, size: 11)
			->addColumn('filename', Type::String)
			->addColumn('mime_type', Type::String)
			->addColumn('size', Type::Int)
			->addColumn('storage_key', Type::String, size: 512)
			->addColumn('uploaded_by_user_id', Type::Int, nullable: true, size: 11)
			->addColumn('uploaded_by_agent', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['song_id'], 'song_files_song_id_index', false)
			->addIndex(['uploaded_by_user_id'], 'song_files_uploaded_by_user_id_index', false)
			->addForeignKey('song_id', 'songs', 'id', 'song_files_song_id_songs_id_fk')
			->addForeignKey('uploaded_by_user_id', 'users', 'id', 'song_files_uploaded_by_user_id_users_id_fk', ReferenceOptionEnum::SetNull)
			->create();

		$this->table('song_tabs')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('song_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('alphatex_content', Type::Text)
			->addColumn('source_type', Type::Enum, enum: ['authored', 'imported_gp'])
			->addColumn('original_file_id', Type::Int, nullable: true, size: 11)
			->addColumn('tempo', Type::Int, nullable: true)
			->addColumn('tuning', Type::String, nullable: true)
			->addColumn('track_count', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['song_id'], 'song_tabs_song_id_index', false)
			->addIndex(['original_file_id'], 'song_tabs_original_file_id_index', false)
			->addForeignKey('song_id', 'songs', 'id', 'song_tabs_song_id_songs_id_fk')
			->addForeignKey('original_file_id', 'song_files', 'id', 'song_tabs_original_file_id_song_files_id_fk', ReferenceOptionEnum::SetNull)
			->create();

		$this->table('song_links')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('song_id', Type::Int, size: 11)
			->addColumn('url', Type::String, size: 2048)
			->addColumn('kind', Type::Enum, enum: ['youtube', 'other'])
			->addColumn('label', Type::String, nullable: true)
			->addColumn('timestamp_seconds', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['song_id'], 'song_links_song_id_index', false)
			->addForeignKey('song_id', 'songs', 'id', 'song_links_song_id_songs_id_fk')
			->create();

		$this->table('song_progress_entries')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('song_id', Type::Int, size: 11)
			->addColumn('practiced_at', Type::Date)
			->addColumn('note', Type::Text, nullable: true)
			->addColumn('tempo_bpm', Type::Int, nullable: true)
			->addColumn('duration_minutes', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['song_id'], 'song_progress_entries_song_id_index', false)
			->addForeignKey('song_id', 'songs', 'id', 'song_progress_entries_song_id_songs_id_fk')
			->create();

		$this->table('song_tags')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('song_id', Type::Int, size: 11)
			->addColumn('tag_id', Type::Int, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['song_id'], 'song_tags_song_id_index', false)
			->addIndex(['tag_id'], 'song_tags_tag_id_index', false)
			->addForeignKey('song_id', 'songs', 'id', 'song_tags_song_id_songs_id_fk')
			->addForeignKey('tag_id', 'tags', 'id', 'song_tags_tag_id_tags_id_fk')
			->create();

		$this->table('song_watchers')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('song_id', Type::Int, size: 11)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['song_id'], 'song_watchers_song_id_index', false)
			->addIndex(['user_id'], 'song_watchers_user_id_index', false)
			->addForeignKey('song_id', 'songs', 'id', 'song_watchers_song_id_songs_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'song_watchers_user_id_users_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('song_watchers')
			->drop();
		$this->table('song_tags')
			->drop();
		$this->table('song_progress_entries')
			->drop();
		$this->table('song_links')
			->drop();
		$this->table('song_tabs')
			->drop();
		$this->table('song_files')
			->drop();
		$this->table('lecture_watchers')
			->drop();
		$this->table('progress_entries')
			->drop();
		$this->table('lecture_tags')
			->drop();
		$this->table('tabs')
			->drop();
		$this->table('lecture_links')
			->drop();
		$this->table('lecture_files')
			->drop();
		$this->table('songs')
			->drop();
		$this->table('lectures')
			->drop();
		$this->table('events')
			->drop();
		$this->table('courses')
			->drop();
		$this->table('tags')
			->drop();
		$this->table('saved_views')
			->drop();
		$this->table('password_reset_tokens')
			->drop();
		$this->table('invitations')
			->drop();
		$this->table('notifications')
			->drop();
		$this->table('workspace_users')
			->drop();
		$this->table('workspaces')
			->drop();
		$this->table('oauth_authorizations')
			->drop();
		$this->table('oauth_clients')
			->drop();
		$this->table('email_verification_tokens')
			->drop();
		$this->table('users')
			->drop();
	}
}
