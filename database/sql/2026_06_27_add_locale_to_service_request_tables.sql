begin;

alter table public.viewings
    add column if not exists locale varchar(10) not null default 'en';

alter table public.search_rentings
    add column if not exists locale varchar(10) not null default 'en';

alter table public.rentings
    add column if not exists locale varchar(10) not null default 'en';

alter table public.careers
    add column if not exists locale varchar(10) not null default 'en';

update public.viewings
set locale = 'en'
where locale is null or btrim(locale) = '';

update public.search_rentings
set locale = 'en'
where locale is null or btrim(locale) = '';

update public.rentings
set locale = 'en'
where locale is null or btrim(locale) = '';

update public.careers
set locale = 'en'
where locale is null or btrim(locale) = '';

alter table public.viewings
    alter column locale set default 'en',
    alter column locale set not null;

alter table public.search_rentings
    alter column locale set default 'en',
    alter column locale set not null;

alter table public.rentings
    alter column locale set default 'en',
    alter column locale set not null;

alter table public.careers
    alter column locale set default 'en',
    alter column locale set not null;

insert into public.migrations (migration, batch)
select
    '2026_06_27_130000_add_locale_to_service_request_tables',
    coalesce((select max(batch) + 1 from public.migrations), 1)
where not exists (
    select 1
    from public.migrations
    where migration = '2026_06_27_130000_add_locale_to_service_request_tables'
);

commit;
