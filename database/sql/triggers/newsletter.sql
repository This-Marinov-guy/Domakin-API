-- First, create a function that will be called by the trigger
CREATE OR REPLACE FUNCTION add_current_year_to_newsletter()
RETURNS TRIGGER AS $$
BEGIN
    -- Set the year field to the current year if it's null
    NEW.year := COALESCE(NEW.year, EXTRACT(YEAR FROM CURRENT_DATE));
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Then, create the trigger that calls this function before insert
CREATE OR REPLACE TRIGGER set_current_year
BEFORE INSERT ON newsletters
FOR EACH ROW
EXECUTE FUNCTION add_current_year_to_newsletter();