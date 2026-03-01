-- List all users that have a matching email in careers
SELECT u.*
FROM users u
WHERE u.email IN (SELECT email FROM careers);
