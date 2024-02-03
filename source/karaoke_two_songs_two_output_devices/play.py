from multiprocessing import Process
import os

os.environ['PYGAME_HIDE_SUPPORT_PROMPT'] = "hide"

import pygame
import pygame._sdl2 as sdl2

def print_devices():
    print('=== start devices ===')
    pygame.mixer.init()
    print(*sdl2.audio.get_audio_device_names(False), sep='\n') # Boolean value determines whether they are Input or Output devices.
    pygame.mixer.quit()
    print('=== end devices ===\n')

def play(devicename, file):
    # flush to make sure message isnt clobbered by another process.
    # hmm, sometimes only 1 process prints, even though they are both running (audio plays)... idk
    print('playing "{}" using "{}" (pid {}, parent {})'.format(file, devicename, os.getpid(), os.getppid()), flush=True)
    pygame.mixer.init(devicename=devicename)
    #pygame.mixer.init()
    s = pygame.mixer.Sound(file)
    s.play()
    while pygame.mixer.get_busy():
        pygame.time.delay(10)
    
if __name__ == '__main__':
    #print_devices()
    
    devicename1 = 'Realtek Digital Output(Optical) (Realtek High Definition Audio)'
    file1 = 'accompaniment.mp3'
    
    devicename2 = 'Speakers (Realtek High Definition Audio)'
    file2 = 'vocal.mp3'

    processes = []
    processes.append(Process(target=play, args=(devicename1, file1)))
    processes.append(Process(target=play, args=(devicename2, file2)))
 
    # start playback 
    for process in processes:
        process.start()
      
    # wait for audio to finish 
    for process in processes:
        process.join()