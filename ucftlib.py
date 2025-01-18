import json, urllib, random
import urllib.request

def request(request:dict, url:str):
    req_json = json.dumps(request)
    req_url = f"{url}?" + urllib.parse.urlencode({"r": req_json})
    req = urllib.request.Request(req_url, headers={'content-type': 'application/json'})
    response = urllib.request.urlopen(req)
    response_json = response.read().decode('utf8')
    response_dict = json.loads(response_json)
    return response_dict

HEX_CHARS = [*"0123456789abcdef"]
FLOW_ID_LEN = 6
CHANNEL_ID_LEN = 10

def gen_flow_id() -> str:
    returned_id = ""
    for i in range(FLOW_ID_LEN):
        returned_id += random.choice(HEX_CHARS)
    return returned_id

def gen_channel_id() -> str:
    returned_id = ""
    for i in range(CHANNEL_ID_LEN):
        returned_id += random.choice(HEX_CHARS)
    return returned_id
