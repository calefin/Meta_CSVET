from pydantic import BaseModel
from typing import Optional, List
from datetime import datetime


class HealthRecordCreate(BaseModel):
    date: Optional[datetime] = None
    weight_grams: Optional[float] = None
    temperature_celsius: Optional[float] = None
    droppings_aspect: Optional[str] = None
    behavior_notes: Optional[str] = None
    symptoms_json: Optional[List[str]] = None
    photos_urls: Optional[List[str]] = None
    severity: str = "normal"


class HealthRecordOut(HealthRecordCreate):
    id: str
    bird_id: str
    user_id: str
    created_at: datetime

    class Config:
        from_attributes = True


class VetConsultCreate(BaseModel):
    description: Optional[str] = None
    priority: str = "medium"
    attachments_urls: Optional[List[str]] = None
    appointment_type: str = "telemedicine"
    scheduled_date: Optional[str] = None
    scheduled_time: Optional[str] = None
    location_address: Optional[str] = None


class VetConsultOut(BaseModel):
    id: str
    health_record_id: Optional[str] = None
    bird_id: str
    requester_user_id: str
    assigned_vet_id: Optional[str] = None
    status: str
    priority: str
    description: Optional[str] = None
    response_text: Optional[str] = None
    response_date: Optional[datetime] = None
    created_at: datetime

    class Config:
        from_attributes = True


class VetRespondRequest(BaseModel):
    response_text: str
    status: str = "responded"
