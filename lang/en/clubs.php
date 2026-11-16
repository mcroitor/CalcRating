<?php

return [
    'flash' => [
        'created' => 'Club created successfully.',
        'updated' => 'Club information updated.',
        'archived' => 'Club archived.',
        'restored' => 'Club restored.',
        'deleted' => 'Club deleted.',
        'staff_updated' => 'Club staff updated.',
        'staff_removed' => 'User removed from club staff.',
        'member_added' => 'Player added to the club.',
        'member_removed' => 'Player removed from the club.',
    ],

    'errors' => [
        'delete_has_tournaments' => 'Cannot delete a club that has tournaments. Archive it instead.',
        'archived_immutable' => 'Cannot change staff, members or tournaments of an archived club.',
        'ineligible_staff' => 'Only active users with the arbiter or admin role can be assigned to club staff.',
        'last_manager' => 'An active club must have at least one active manager.',
        'cannot_create_tournament' => 'You do not have permission to create tournaments in this club.',
        'arbiter_not_in_club' => 'The selected arbiter must belong to this club staff or be an administrator.',
    ],

    'warnings' => [
        'sole_manager_affected' => 'Attention: this user was the sole active manager of club(s): :clubs. Assign a new manager.',
    ],

    'fields' => [
        'name' => 'Club name *',
        'code' => 'Short code / abbreviation',
        'city' => 'City',
        'address' => 'Address',
        'website' => 'Website',
        'description' => 'Description and contacts',
        'logo' => 'Club logo (JPG, PNG, WEBP, up to 2 MB)',
        'remove_logo' => 'Remove current logo',
        'manager' => 'Initial club manager *',
    ],

    'index' => [
        'title' => 'Chess clubs',
        'create' => '+ Create club',
        'search_label' => 'Search',
        'search_placeholder' => 'Name, code or city...',
        'status_label' => 'Status',
        'all_statuses' => 'All statuses',
        'not_found' => 'No clubs found.',
        'members_count' => ':count member|:count members',
        'tournaments_count' => ':count tournament|:count tournaments',
        'view' => 'Open club',
    ],

    'create' => [
        'title' => 'Create club',
        'submit' => 'Create club',
    ],

    'edit' => [
        'title' => 'Edit club: :name',
        'back' => '← Back to club',
        'submit' => 'Save changes',
    ],

    'show' => [
        'back' => '← All clubs',
        'edit' => 'Edit',
        'archive' => 'Archive',
        'archive_confirm' => 'Archive this club? New tournaments and roster changes will be disabled.',
        'restore' => 'Restore',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete this club permanently?',
        'archived_notice' => 'This club is archived. History remains public, while creating tournaments and editing members or staff are disabled.',
        'create_tournament' => '+ Create tournament',
        'website' => 'Website',
        'location' => 'Location',
        'tournaments_title' => 'Club tournaments',
        'no_tournaments' => 'This club has no tournaments yet.',
        'members_title' => 'Club players',
        'add_member' => 'Add player to club',
        'member_search_placeholder' => 'Last name or first name...',
        'no_players_found' => 'No available players found.',
        'add_member_btn' => '+ Add',
        'remove_member_confirm' => 'Remove this player from the club?',
        'remove_member' => 'Remove',
        'no_members' => 'No players in this club yet.',
        'staff_title' => 'Club staff',
        'add_staff' => 'Assign staff member',
        'select_user' => 'Select user',
        'assign_btn' => 'Assign',
        'remove_staff_confirm' => 'Remove this user from club staff?',
        'remove_staff' => 'Remove',
        'no_staff' => 'No staff assigned.',
    ],
];
