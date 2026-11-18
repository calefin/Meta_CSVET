import uvicorn
from fastapi import FastAPI, File, UploadFile, Form, HTTPException
from fastapi.responses import FileResponse
from fastapi.middleware.cors import CORSMiddleware
from omr_processor import OMRProcessor
import json

app = FastAPI(title="ProvaGen OpenCV Backend", version="1.0.0")

# Enable CORS for Flutter web / local clients
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
def read_root():
    return {"status": "online", "message": "ProvaGen OpenCV OMR Python Backend is running."}

@app.get("/favicon.ico", include_in_schema=False)
def favicon():
    return FileResponse("favicon.svg", media_type="image/svg+xml")

@app.post("/api/correct")
async def correct_sheet(
    image: UploadFile = File(...),
    quiz_id: str = Form(...),
    keys: str = Form(...),
    settings: str = Form(...)
):
    try:
        image_bytes = await image.read()
        
        # Load and validate json data
        try:
            keys_data = json.loads(keys)
            settings_data = json.loads(settings)
        except Exception as json_err:
            raise HTTPException(status_code=400, detail=f"Invalid JSON in form parameters: {str(json_err)}")

        # Process the sheet using OpenCV
        result = OMRProcessor.process_sheet(image_bytes, keys_data, settings_data)
        
        return result

    except ValueError as val_err:
        raise HTTPException(status_code=400, detail=str(val_err))
    except Exception as e:
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Error during OMR processing: {str(e)}")

if __name__ == "__main__":
    uvicorn.run("app:app", host="0.0.0.0", port=5000, reload=True)
