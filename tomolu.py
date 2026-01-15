import random
import datetime
from faker import Faker

# Initialize Faker with Nigerian locale
fake = Faker('en_NG')

# Configuration
NUM_RECORDS = 550  # Generating 550 records
TABLE_NAME = "profiles"
OUTPUT_FILE = "dummy_profiles_nigeria.sql"

# --- Nigerian Context Data ---
STATES = [
    "Abia", "Adamawa", "Akwa Ibom", "Anambra", "Bauchi", "Bayelsa", "Benue", "Borno", 
    "Cross River", "Delta", "Ebonyi", "Edo", "Ekiti", "Enugu", "Gombe", "Imo", 
    "Jigawa", "Kaduna", "Kano", "Katsina", "Kebbi", "Kogi", "Kwara", "Lagos", 
    "Nasarawa", "Niger", "Ogun", "Ondo", "Osun", "Oyo", "Plateau", "Rivers", 
    "Sokoto", "Taraba", "Yobe", "Zamfara", "FCT"
]

RELIGIONS = ["Christianity", "Islam", "Traditional", "Other"]
LANGUAGES = ["Yoruba", "Igbo", "Hausa", "Edo", "Efik", "English", "Pidgin"]
MODES = ["UTME", "Direct Entry"]
PROG_TYPES = ["Full Time", "Part Time"]
DISABILITIES = ["None", "Visual Impairment", "Hearing Impairment", "Mobility Impairment", "None", "None", "None"] # Weighted towards None

def generate_jamb_number(year):
    # Example format: 2023948572AB
    digits = str(random.randint(10000000, 99999999))
    letters = "".join(random.choices("ABCDEFGHJKLMNPQRSTUVWXYZ", k=2))
    return f"{year}{digits}{letters}"

print(f"Generating {NUM_RECORDS} records for {TABLE_NAME}...")

# Columns list (Updated to include state_of_origin)
# Assumed order: state_of_origin comes after marital_status based on typical schema grouping
columns = [
    "user_id", "department_id", "secondary_department_ids", "level_id", "hall_id", 
    "gender", "dob", "marital_status", "state_of_origin", "institutional_email", "alternative_email", 
    "religion", "language_spoken", "contact_address", "state", "city", 
    "postal_address", "jamb_number", "entry_year_id", "mode_of_admission", 
    "programme_type", "programme_type_id", "disability", "photo", "signature", 
    "academic_session_id", "lock_session", "needs_course_requirement", 
    "created_at", "updated_at"
]

with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
    # Header
    f.write(f"-- Dummy Data for {TABLE_NAME} Table\n")
    f.write(f"-- Generated on {datetime.datetime.now()}\n\n")
    
    f.write(f"INSERT INTO `{TABLE_NAME}` ({', '.join(columns)}) VALUES\n")
    
    batch = []
    
    for i in range(1, NUM_RECORDS + 1):
        # 1. User Info
        user_id = 2000 + i # Start user_ids from 2001
        fname = fake.first_name()
        lname = fake.last_name()
        
        # 2. Demographics
        gender = random.choice(['Male', 'Female'])
        dob = fake.date_of_birth(minimum_age=16, maximum_age=30).strftime('%Y-%m-%d')
        marital = random.choice(['Single', 'Single', 'Single', 'Married']) 
        
        # Distinct States
        state_origin = random.choice(STATES) # Cultural origin
        state_residence = random.choice(STATES) # Current location (Address)
        
        religion = random.choice(RELIGIONS)
        language = random.choice(LANGUAGES)
        
        # 3. Academic Info
        department_id = random.randint(1, 20) 
        sec_dept = "'[1, 2]'" if random.random() < 0.1 else "NULL"
        level_id = random.choice([100, 200, 300, 400, 500])
        hall_id = random.randint(1, 9) 
        
        # 4. Admission Info
        entry_year = random.randint(2019, 2024)
        jamb = generate_jamb_number(entry_year)
        mode = random.choice(MODES)
        prog_type = random.choice(PROG_TYPES)
        prog_type_id = 1 if prog_type == "Full Time" else 2
        
        # 5. Contact Info
        inst_email = f"{fname.lower()}.{lname.lower()}{random.randint(1,99)}@student.ui.edu.ng"
        alt_email = fake.email()
        address = fake.street_address().replace("'", "''") 
        city = fake.city()
        postal = fake.postcode()
        
        # 6. Metadata
        disability = random.choice(DISABILITIES)
        photo = f"uploads/photos/{user_id}.jpg"
        signature = f"uploads/signatures/{user_id}.png"
        acad_session = 2024
        lock_session = random.choice([0, 1])
        needs_req = random.choice([0, 1])
        created_at = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        updated_at = created_at

        # Construct Value String
        value_str = (
            f"({user_id}, {department_id}, {sec_dept}, {level_id}, {hall_id}, "
            f"'{gender}', '{dob}', '{marital}', '{state_origin}', '{inst_email}', '{alt_email}', "
            f"'{religion}', '{language}', '{address}', '{state_residence}', '{city}', "
            f"'{postal}', '{jamb}', {entry_year}, '{mode}', "
            f"'{prog_type}', {prog_type_id}, '{disability}', '{photo}', '{signature}', "
            f"{acad_session}, {lock_session}, {needs_req}, '{created_at}', '{updated_at}')"
        )
        batch.append(value_str)

    # Join all values with commas and add semicolon at the end
    f.write(",\n".join(batch) + ";\n")

print(f"Successfully generated {OUTPUT_FILE}")