from ucftlib import *
import os, time, hashlib, threading, json, sys

server = "http://localhost:8000/ucft.php"

class Channel():
    class FileNotFound(BaseException): ...
    class NotAFile(BaseException): ...
    class ChannelOperationFailed(BaseException): ...

    def __init__(self, path:str):
        self.relpath = path
        self.check_path()
        self.id = gen_channel_id()
        self.byte_index = 0
        self.active = False
        self.file = open(self.relpath, encoding="unicode_escape")
        self.file_content = self.file.read()
        self.file_content_len = len(self.file_content)
        self.checksum = hashlib.md5(self.file_content.encode("unicode_escape")).hexdigest()
        self.create()

    def check_fail(self, response:dict):
        if not response["error"] is None: raise self.ChannelOperationFailed(response["error"])

    def request(self, request_:dict): self.check_fail(request(request_, server))

    def get_byte(self): return ord(self.file_content[self.byte_index])

    def create(self):
        self.request({"command": "create_channel", "pubid": self.id, "relpath": self.relpath, "diffusion_byte": self.get_byte(), "checksum": self.checksum})
        self.active = True

    def delete(self):
        self.request({"command": "delete_channel", "pubid": self.id})
        self.active = False
        self.file.close()

    def step(self):
        self.byte_index += 1
        try: self.get_byte()
        except: self.delete()
        else:
            if not self.active: self.create()
            else: self.request({"command": "change_channel", "pubid": self.id, "diffusion_byte": self.get_byte()})

    def get_advancement(self) -> int:
        return (self.byte_index + 1) * 100 / self.file_content_len

    def check_path(self):
        if not os.path.exists(self.relpath): raise self.FileNotFound(self.relpath)
        if not os.path.isfile(self.relpath): raise self.NotAFile(self.relpath)
        self.relpath = os.path.relpath(self.relpath)

class Flow():
    class TargetNotFound(BaseException): ...
    class FlowOperationFailed(BaseException): ...
    
    def __init__(self, target_path:str, rate:int):
        self.target_path = target_path
        self.rate = rate
        self.id = gen_flow_id()
        self.channels:list[Channel] = []
        self.active = False
        self.check_target()
        self.create()

    def diffuse_file(self, path:str):
        channel = Channel(path)
        self.channels.append(channel)
        print(f"Channel created for '{channel.relpath}', public id : {channel.id}")
        time.sleep(self.rate / 1000)
        while channel.active:
            channel.step()
            time.sleep(self.rate / 1000)
        print(f"Channel terminated for '{channel.relpath}', public id : {channel.id}")
        self.channels.remove(channel)

    def check_target(self):
        if not os.path.exists(self.target_path): raise self.TargetNotFound(self.target_path)

    def check_fail(self, response:dict):
        if not response["error"] is None: raise self.FlowOperationFailed(response["error"])

    def request(self, request_:dict): self.check_fail(request(request_, server))

    def get_channels_id(self):
        channels = []
        for channel in self.channels: channels.append(channel.id)
        return channels

    def create(self):
        self.request({"command": "create_flow", "pubid": self.id, "channels": json.dumps(self.get_channels_id()), "rate": self.rate})
        self.active = True

    def delete(self):
        self.request({"command": "delete_flow", "pubid": self.id})
        self.active = False

    def start_diffusion(self):
        if os.path.isdir(self.target_path):
            for root, dirs, files in os.walk(self.target_path, topdown=False):
                for file in files:
                    file = os.path.join(self.target_path, file)
                    threading.Thread(target=self.diffuse_file, args=(file,)).start()
        else:
            threading.Thread(target=self.diffuse_file, args=(self.target_path,)).start()

    def get_advancement(self):
        advancements = []
        for channel in self.channels: advancements.append(channel.get_advancement())
        if advancements: return round(sum(advancements) / len(advancements), 2)
        else: return 0

    def update(self):
        self.request({"command": "change_flow", "pubid": self.id, "channels": json.dumps(self.get_channels_id())})

if __name__ == "__main__":
    args = sys.argv[1:]
    rate = 300
    if not len(args) in [1, 2]:
        print(f"Usage: distributor <target path> <rate = {rate}>")
        sys.exit(0)
    target_path = args[0]
    if len(args) == 2: rate = int(args[1])
    flow = Flow(target_path, rate)
    print(f"Flow created, public id : {flow.id}.")
    command = ""
    while not command.lower() in ["y", "n", "yes", "no"]:
        command = input("Do you want to start diffusion ? [Y/n] ")
        
    if command.lower() in ["n", "no"]:
        print("Starting diffusion...")

    flow.start_diffusion()
    print("Diffusion started.")

    i = 0
    last_channels = []
    
    while flow.active:
        print(f"{len(flow.channels)} channels remaining. Advancement: {flow.get_advancement()}%.", end="\r")
        if last_channels != flow.channels:
            threading.Thread(target=flow.update).start()
            last_channels = flow.channels
        if not len(flow.channels) and i >= 300000:
            flow.delete()
            break
        i += 1

    print("Diffusion finished.")
