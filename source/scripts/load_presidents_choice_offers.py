# log into president's choice website, so that offers are loaded.

from selenium import webdriver 
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By 
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import json

### CONFIG
email = ""
password = "" 
### END CONFIG

chrome_options = Options()
chrome_options.add_argument("--headless=new") 
chrome_options.set_capability('goog:loggingPrefs', {'performance': 'ALL'})
driver = webdriver.Chrome(options=chrome_options)

# print versions
browserVersion = driver.capabilities['browserVersion']
driverVersion = driver.capabilities['chrome']['chromedriverVersion'].split(' ')[0]
print("chrome browser is version "+browserVersion)
print("chrome driver is version "+driverVersion)
print()

# default useragent includes HeadlessChrome, replace it with a normal-looking user agent. fill in matching chrome browser version.
# without setting a useragent we get a 429 trying to log in.
# hmm we still get a 429 logging in if outside canada. in canada this all works. 
# wonder if location is being considered, or triggering more validation? manually in-browser it works.
driver.execute_cdp_cmd('Network.setUserAgentOverride', {"userAgent": f'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{browserVersion} Safari/537.36'})

login_url = "https://www.pcoptimum.ca/login"
success = False

try:
    print("loading login page...")
    driver.get(login_url)

    print("waiting for email input to be present...")
    email_element = WebDriverWait(driver, 5).until(
        EC.presence_of_element_located((By.ID, "email"))
    )
    
    print("email input is present - fill in email and password")
    email_element.send_keys(email)
    # assuming #password is also present if #email is
    driver.find_element(by=By.ID, value="password").send_keys(password) 
    
    print("submit login form")
    driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click() 
    
    print("waiting for offers page to load...")
    offers_element = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.CLASS_NAME, "header-offer-loading-indicator-loaded"))
    )
    print(offers_element.text)
    print("success")
    success = True
    
    # couldnt find offers in window object. look at performance log to find xhr responses.
    performance_logs = driver.get_log("performance")
    for performance_log in performance_logs:
        performance_log_json = json.loads(performance_log["message"])
        message = performance_log_json["message"]
        # look for "v1/member/offers" xhr response, ignoring the preflight message.
        if  'Network.responseReceived' == message["method"] and \
            "Preflight" != message["params"].get("type") and \
            "v1/member/offers" in message["params"]["response"]["url"]:
            offers_response = driver.execute_cdp_cmd('Network.getResponseBody', {'requestId': message["params"]["requestId"]})
            offers = json.loads(offers_response["body"])
            #print(offers)
            # todo: print list of offers

finally:
    if not success:
        print("** did not succeed ***")
        driver.save_screenshot("pc-fail-screenshot.png")
        print("saved screenshot")
    print("driver quit")
    driver.quit()