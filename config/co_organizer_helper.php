<?php
/**
 * Co-Organizer Helper Functions
 * Use these functions to check if a user can access/modify events
 */

/**
 * Check if user can view an event (as owner or co-organizer with any permission)
 */
function canViewEvent($conn, $event_id, $user_id) {
    $query = "SELECT 
                (e.organizer_id = ?) as is_owner,
                (SELECT COUNT(*) FROM event_co_organizers 
                 WHERE event_id = ? AND organizer_id = ? AND status = 'accepted') as is_co_organizer
              FROM events e 
              WHERE e.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $event_id, $user_id, $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        return ($row['is_owner'] == 1 || $row['is_co_organizer'] > 0);
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Check if user can edit an event (as owner or co-organizer with edit/full permission)
 */
function canEditEvent($conn, $event_id, $user_id) {
    $query = "SELECT 
                (e.organizer_id = ?) as is_owner,
                (SELECT permissions FROM event_co_organizers 
                 WHERE event_id = ? AND organizer_id = ? AND status = 'accepted' 
                 AND permissions IN ('edit', 'full') LIMIT 1) as co_org_permission
              FROM events e 
              WHERE e.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $event_id, $user_id, $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        return ($row['is_owner'] == 1 || in_array($row['co_org_permission'], ['edit', 'full']));
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Check if user can manage co-organizers (as owner or co-organizer with full permission)
 */
function canManageCoOrganizers($conn, $event_id, $user_id) {
    $query = "SELECT 
                (e.organizer_id = ?) as is_owner,
                (SELECT permissions FROM event_co_organizers 
                 WHERE event_id = ? AND organizer_id = ? AND status = 'accepted' 
                 AND permissions = 'full' LIMIT 1) as co_org_permission
              FROM events e 
              WHERE e.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $event_id, $user_id, $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        return ($row['is_owner'] == 1 || $row['co_org_permission'] == 'full');
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Get user's permission level for an event
 * Returns: 'owner', 'full', 'edit', 'view', or false
 */
function getEventPermission($conn, $event_id, $user_id) {
    $query = "SELECT 
                (e.organizer_id = ?) as is_owner,
                (SELECT permissions FROM event_co_organizers 
                 WHERE event_id = ? AND organizer_id = ? AND status = 'accepted' LIMIT 1) as co_org_permission
              FROM events e 
              WHERE e.id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $event_id, $user_id, $event_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        
        if($row['is_owner'] == 1) {
            return 'owner';
        } elseif($row['co_org_permission']) {
            return $row['co_org_permission'];
        }
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Get all events a user can manage (as owner or accepted co-organizer)
 */
function getUserManageableEvents($conn, $user_id) {
    $query = "SELECT DISTINCT e.*, 
              CASE 
                WHEN e.organizer_id = ? THEN 'owner'
                ELSE (SELECT permissions FROM event_co_organizers 
                      WHERE event_id = e.id AND organizer_id = ? AND status = 'accepted' LIMIT 1)
              END as user_permission,
              (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
              FROM events e
              LEFT JOIN event_co_organizers eco ON e.id = eco.event_id AND eco.organizer_id = ? AND eco.status = 'accepted'
              WHERE e.organizer_id = ? OR eco.id IS NOT NULL
              ORDER BY e.event_date DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iiii", $user_id, $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $events = [];
    while($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $events;
}
?>