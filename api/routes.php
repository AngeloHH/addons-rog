<?php

/**
 * Addons ROG - HTTP route registry
 *
 * - Map each method and path to a service action and access requirement.
 * - Route tuples contain method, path, class, action, access, and status.
 */

declare(strict_types=1);

// =============================================================================
// ROUTE DEFINITIONS
// =============================================================================

return [
    // Setup routes use installer authorization instead of a regular session.
    ['GET','/setup/status',Rog\Services\InstallService::class,'status','setup',200],
    ['GET','/setup/requirements',Rog\Services\InstallService::class,'requirements','setup',200],
    ['POST','/setup/database',Rog\Services\InstallService::class,'database','setup',200],
    ['POST','/setup/install',Rog\Services\InstallService::class,'install','setup',201],
    // Keep literal paths before parameterized paths that could match them.
    ['GET','/default-icon.svg',Rog\Services\CatalogService::class,'default_icon','public',200],
    ['POST','/auth/invitations/accept',Rog\Services\AdminService::class,'accept_invitation','user',200],
    ['GET','/admin/activity/export',Rog\Services\ActivityService::class,'export','staff',200],
    ['POST','/auth/verify/resend',Rog\Services\AuthService::class,'resend','public',200],
    ['POST','/admin/invitations',Rog\Services\AdminService::class,'invite','founder',201],
    ['GET','/addons/check-name',Rog\Services\CatalogService::class,'check_name','user',200],
    ['GET','/admin/submissions',Rog\Services\SubmissionService::class,'queue','staff',200],
    ['GET','/admin/dashboard',Rog\Services\StatsService::class,'dashboard','staff',200],
    ['GET','/admin/accounts',Rog\Services\AdminService::class,'accounts','staff',200],
    ['GET','/admin/activity',Rog\Services\ActivityService::class,'listing','staff',200],
    ['POST','/auth/register',Rog\Services\AuthService::class,'register','public',201],
    ['GET','/admin/reports',Rog\Services\ReportService::class,'listing','staff',200],
    ['GET','/admin/counts',Rog\Services\AdminService::class,'counts','staff',200],
    ['GET','/auth/session',Rog\Services\AuthService::class,'me','public',200],
    ['POST','/auth/logout',Rog\Services\AuthService::class,'logout','public',200],
    ['POST','/auth/verify',Rog\Services\AuthService::class,'verify','public',200],
    ['POST','/submissions',Rog\Services\SubmissionService::class,'create','uploader',201],
    ['GET','/admin/stats',Rog\Services\StatsService::class,'stats','staff',200],
    ['GET','/submissions',Rog\Services\SubmissionService::class,'listing','user',200],
    ['POST','/auth/login',Rog\Services\AuthService::class,'login','public',200],
    ['GET','/admin/live',Rog\Services\StatsService::class,'live','staff',200],
    ['GET','/site/stats',Rog\Services\CatalogService::class,'site_stats','public',200],
    ['POST','/requests',Rog\Services\RequestService::class,'create','user',201],
    ['GET','/requests',Rog\Services\RequestService::class,'listing','public',200],
    ['GET','/taxonomy',Rog\Services\CatalogService::class,'taxonomy','public',200],
    ['POST','/contact',Rog\Services\ContactService::class,'send','public',201],
    ['POST','/reports',Rog\Services\ReportService::class,'create','user',201],
    ['POST','/uploads',Rog\Services\FileService::class,'upload','uploader',201],
    ['GET','/contact',Rog\Services\ContactService::class,'context','public',200],
    ['GET','/addons',Rog\Services\CatalogService::class,'listing','public',200],
    ['GET','/health',Rog\Services\CatalogService::class,'health','public',200],
    ['POST','/media',Rog\Services\FileService::class,'upload','uploader',201],
    ['GET','/about',Rog\Services\ProfileService::class,'about','public',200],
    ['GET','/home',Rog\Services\CatalogService::class,'home','public',200],
    // Named placeholders become request parameters after path matching.
    ['POST','/admin/requests/{requestId}/notify-authors',Rog\Services\RequestService::class,'notify_authors','staff',202],
    ['POST','/admin/submissions/{submissionId}/decision',Rog\Services\SubmissionService::class,'decision','staff',200],
    ['GET','/admin/submissions/{submissionId}/file',Rog\Services\SubmissionService::class,'file','staff',200],
    ['POST','/admin/accounts/{accountId}/message',Rog\Services\AdminService::class,'message','staff',201],
    ['POST','/admin/requests/{requestId}/action',Rog\Services\RequestService::class,'action','staff',200],
    ['POST','/submissions/{submissionId}/submit',Rog\Services\SubmissionService::class,'submit','uploader',200],
    ['GET','/admin/submissions/{submissionId}',Rog\Services\SubmissionService::class,'inspect','staff',200],
    ['POST','/admin/reports/{reportId}/action',Rog\Services\ReportService::class,'action','staff',200],
    ['PATCH','/admin/accounts/{accountId}',Rog\Services\AdminService::class,'update_account','staff',200],
    ['PATCH','/submissions/{submissionId}',Rog\Services\SubmissionService::class,'update','uploader',200],
    ['PUT','/requests/{requestId}/follow',Rog\Services\RequestService::class,'follow','user',200],
    ['GET','/download-files/{versionId}',Rog\Services\DownloadService::class,'signed','public',200],
    ['GET','/submissions/{submissionId}',Rog\Services\SubmissionService::class,'show','user',200],
    ['PUT','/requests/{requestId}/vote',Rog\Services\RequestService::class,'vote','user',200],
    ['GET','/users/{username}/journal',Rog\Services\ProfileService::class,'journal','public',200],
    ['GET','/users/{username}/reviews',Rog\Services\ProfileService::class,'reviews','public',200],
    ['PUT','/addons/{slug}/my-comment',Rog\Services\CommentService::class,'replace','user',200],
    ['GET','/packs/{packId}/download',Rog\Services\DownloadService::class,'pack','public',200],
    ['GET','/users/{username}/addons',Rog\Services\ProfileService::class,'addons','public',200],
    ['PUT','/users/{username}/follow',Rog\Services\ProfileService::class,'follow','user',200],
    ['GET','/addons/{slug}/comments',Rog\Services\CommentService::class,'listing','public',200],
    ['PUT','/addons/{slug}/favorite',Rog\Services\CatalogService::class,'favorite','user',200],
    ['GET','/downloads/{versionId}',Rog\Services\DownloadService::class,'version','public',200],
    ['GET','/profiles/{username}',Rog\Services\ProfileService::class,'show','public',200],
    ['GET','/files/{fileId}',Rog\Services\FileService::class,'media','public',200],
    ['GET','/addons/{slug}',Rog\Services\CatalogService::class,'show','public',200],
];
