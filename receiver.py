from ucftlib import *
import time, threading, sys, os

server = "http://localhost:8000/ucft.php"

def create_not_existant_dir(file_path: str):
    dir_path = os.path.dirname(file_path)
    
    if dir_path and not os.path.exists(dir_path):
        os.makedirs(dir_path)

def listen_to_channel(pubid:str):
    global received_path
    
    channel = request({"command": "get_channel_info", "pubid": pubid}, server)
    if not channel["error"] is None: return
    channel_path = channel["info"]["relpath"]
    channel_byte = channel["info"]["diffusion_byte"]

    file_path = os.path.join(received_path, channel_path)

    create_not_existant_dir(file_path)

    file = open(file_path, "a+")
    file.write(chr(channel_byte))
    file.close()

if __name__ == "__main__":
    args = sys.argv[1:]
    received_path = "."
    if not len(args) in [1, 2]:
        print(f"Usage: receiver <public id> <received path = .>")
        sys.exit(0)
    pubid = args[0]
    if len(args) == 2: received_path = args[1]
    if not os.path.exists(received_path):
        print("Received path not found.")
    if not os.path.isdir(received_path):
        print("Received path is not a directory.")
    res_check = request({"command": "check_flow", "pubid": pubid}, server)
    if not res_check["error"] is None:
        print(f"Flow not found.")
        sys.exit(0)
    rate = request({"command": "get_flowrate", "pubid": pubid}, server)["rate"]
    print(f"Listening to the flow {pubid} with a flowrate of {rate} ms.")
    while True:
        res_check = request({"command": "check_flow", "pubid": pubid}, server)
        if not res_check["error"] is None: break

        channels = request({"command": "get_flow_channels", "pubid": pubid}, server)["channels"]
        for channel in channels:
            threading.Thread(target=listen_to_channel, args=(channel,)).start()
        
        time.sleep(rate / 1000)
