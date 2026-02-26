grant usage on schema public to authenticated;

grant select, insert, update, delete
on table public.users
to authenticated;

grant select, insert, update, delete
on table public.user_settings
to authenticated;