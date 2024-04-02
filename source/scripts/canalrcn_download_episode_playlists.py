from seleniumwire import webdriver
from seleniumwire.utils import decode
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By 
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import json
import time
import urllib.request

chrome_options = Options()
chrome_options.add_extension("ublock.crx"); # skip preroll ads cutting down on wait time.
chrome_options.add_argument("--headless=new") 
chrome_options.add_experimental_option('excludeSwitches', ['enable-logging']) # suppresses some certificate warnings on windows.
chrome_options.set_capability('goog:loggingPrefs', {'performance': 'ALL'})
driver = webdriver.Chrome(options=chrome_options)

# print versions
browserVersion = driver.capabilities['browserVersion']
driverVersion = driver.capabilities['chrome']['chromedriverVersion'].split(' ')[0]
print("chrome browser is version "+browserVersion)
print("chrome driver is version "+driverVersion)
print()

# default useragent includes HeadlessChrome, replace it with a normal-looking user agent. fill in matching chrome browser version.
driver.execute_cdp_cmd('Network.setUserAgentOverride', {"userAgent": f'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{browserVersion} Safari/537.36'})

go = True
page=0
base_url = 'https://www.canalrcn.com/rigo/'
episode_list_url=base_url+"capitulos/?ord=&page="
outdir="rigo_m3u8/"

# could split this into 2 parts, "get urls for episode pages" and "get m3u8 from episode page".
try:
    print("get episode list")
    while go:
        with urllib.request.urlopen(episode_list_url+str(page)) as response:
            episodes = json.load(response)
            print("page "+str(page))
            # this check doesnt work @todo
            if not episodes.count:
                go = False
            else:
                for episode in episodes:
                    path = episode['path']
                    print()
                    print(path)
                    name = path.replace('capitulos/', '')
                    download_episode_m3u8(outdir, name, base_url+path)
                page=page+1
    
    print("done")
    
finally:
    print("driver quit")
    driver.quit()
    
def download_episode_m3u8(outdir, episode_name, episode_url):
    # clear requests from a previous run
    del driver.requests
    
    print("loading episode page...")
    driver.get(episode_url)

    print("waiting for player to be present...")
    player_element = WebDriverWait(driver, 5).until(
        EC.presence_of_element_located((By.CLASS_NAME, "dailymotion-player"))
    )
    print("player present, sleep 3 for autoplay to start")
    time.sleep(3)
      
    # selenium-wire adds driver.requests. 
    # wasnt able to find m3u8 with chrome log or js snippet, selenium-wire worked.
    # dump m3u8 file.
    print("looking for m3u8")
    for request in driver.requests:
        if request.response and "m3u8" in request.url and "dailymotion.com" in request.url:
            body = decode(request.response.body, request.response.headers.get('Content-Encoding', 'identity'))
            outfile = outdir+episode_name+'.m3u8'
            with open(outfile, mode='wb') as writer:
                writer.write(body)
            print("found m3u8, wrote to "+outfile)
            return
        
    print("** did not succeed ***")
    driver.save_screenshot("fail-screenshot.png")
    print("driver requests")
    print(driver.requests)
    print("current url: "+episode_url)
    print("saved screenshot")
    exit()
