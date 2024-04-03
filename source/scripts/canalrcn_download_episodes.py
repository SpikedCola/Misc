from seleniumwire import webdriver
from seleniumwire.utils import decode
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By 
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import json
import time
import urllib.request
import subprocess
from pathlib import Path

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

# config
show = 'rigo'
# /config

base_url = 'https://www.canalrcn.com/'+show+'/'
# list starts at page 0, but skips a bunch of episodes that are on the base_url page... hmm. @todo.
page=0
episode_list_url=base_url+"capitulos/?ord=&page="
# will check playlist_outdir if playlist exists, if it does we will skip downloading playlist and video.
playlist_outdir=show+"_m3u8/"
video_outdir="D:/"+show+"/"
# mkdir if not exists
Path(playlist_outdir).mkdir(parents=True, exist_ok=True)
Path(video_outdir).mkdir(parents=True, exist_ok=True)

def download_video(playlist_file, video_file):
    print("download m3u8 "+playlist_file+" ---> "+video_file)
    ffmpeg = [
        'ffmpeg',
        '-loglevel',
        'warning',
        '-stats',
        '-protocol_whitelist',
        'file,http,https,tcp,tls',
        '-allowed_extensions',
        'ALL',
        '-i',
        playlist_file,
        '-bsf:a',
        'aac_adtstoasc',
        '-c',
        'copy',
        video_file
    ]
    subprocess.run(ffmpeg)
    
def find_download_episode_playlist(episode_url, playlist_file):
    # clear requests from a previous run
    del driver.requests
    
    print("loading episode page...")
    driver.get(episode_url)

    print("waiting for player to be present...")
    player_element = WebDriverWait(driver, 5).until(
        EC.presence_of_element_located((By.CLASS_NAME, "dailymotion-player"))
    )
    print("player present, sleep 5 for autoplay to start...")
    time.sleep(5)
      
    # selenium-wire adds driver.requests. 
    # wasnt able to find m3u8 with chrome log or js snippet, selenium-wire worked.
    # dump m3u8 file.
    print("look for m3u8...")
    for request in driver.requests:
        if request.response and "m3u8" in request.url and "dailymotion.com" in request.url:
            body = decode(request.response.body, request.response.headers.get('Content-Encoding', 'identity'))
            with open(playlist_file, mode='wb') as writer:
                writer.write(body)
            print("found m3u8, wrote to "+playlist_file)
            return
        
    print("** did not succeed ***")
    driver.save_screenshot("fail-screenshot.png")
    print("driver requests")
    print(driver.requests)
    print("current url: "+episode_url)
    print("saved screenshot")
    exit()

# could split this into 2 parts, "get urls for episode pages" and "get m3u8 from episode page".
try:
    print("get episode list")
    while True:
        with urllib.request.urlopen(episode_list_url+str(page)) as response:
            episodes = json.load(response)
            print("page "+str(page))
            if not episodes:
                # out of work
                break
            else:
                for episode in episodes:
                    path = episode['path']
                    episode_url = base_url+path
                    print()
                    print(path)
                    name = path.replace('capitulos/', '')
                    playlist_file = playlist_outdir+name+'.m3u8'
                    video_file = video_outdir+name+'.mp4'
                    if Path(playlist_file).is_file():
                        # @todo better
                        print("playlist "+playlist_file+" exists, skipping download")
                    else:
                        find_download_episode_playlist(episode_url, playlist_file)
                        download_video(playlist_file, video_file)
                page=page+1
    
    print("done")
    
finally:
    print("driver quit")
    driver.quit()
    