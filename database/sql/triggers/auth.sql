create or replace function public.custom_access_token_hook(event jsonb) 
returns jsonb language plpgsql as $$
declare
  claims jsonb;
  user_roles text;
  user_status text;
  user_email text;
begin
  -- Ensure we have valid input
  if event is null or event->>'claims' is null then
    return event;
  end if;

  claims := event->'claims';
  
  -- Safely extract email
  user_email := coalesce(claims->'user_metadata'->>'email', '');

  -- Safely query the database
  begin
    select roles, status 
    into user_roles, user_status
    from public.users 
    where email = user_email;
    
    -- If no user found, use defaults
    if not found then
      user_roles := 'user';
      user_status := '1';
    end if;
  exception when others then
    user_roles := 'user';
    user_status := '1';
  end;

  -- Ensure we're creating valid JSON
  claims := claims - 'user_metadata';
  claims := jsonb_set(claims, '{user_roles}', to_jsonb(coalesce(user_roles, 'user')));
  claims := jsonb_set(claims, '{user_status}', to_jsonb(coalesce(user_status, '1')));
  
  -- Ensure we return valid JSON
  return jsonb_set(event, '{claims}', claims);
end;
$$;

-- Refresh permissions
grant usage on schema public to supabase_auth_admin;
grant select on public.users to supabase_auth_admin;
grant execute on function public.custom_access_token_hook to supabase_auth_admin;
revoke execute on function public.custom_access_token_hook from authenticated, anon, public;