<?php
// Inquiries are always saved to api/data/inquiries.csv (blocked from the web).
// Set notify_email to also receive each inquiry by email. from_email should be a
// mailbox on this domain (create it in Hostinger hPanel) so the mail is delivered.
return [
    'notify_email' => '',
    'from_email' => '',
];
