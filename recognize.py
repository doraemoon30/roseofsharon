# recognize.py (microphone version)
from vosk import Model, KaldiRecognizer
import sys, json, queue, sounddevice as sd

language = sys.argv[1] if len(sys.argv) > 1 else "en"
model_path = "vosk-model-small-en-us-0.15" if language == "en" else "vosk-model-tl-ph-generic-0.6"

model = Model(model_path)
q = queue.Queue()

def callback(indata, frames, time, status):
    q.put(bytes(indata))

rec = KaldiRecognizer(model, 16000)

with sd.RawInputStream(samplerate=16000, blocksize=8000, dtype='int16',
                       channels=1, callback=callback):
    while True:
        data = q.get()
        if rec.AcceptWaveform(data):
            result = json.loads(rec.Result())
            print(result["text"])
            break
