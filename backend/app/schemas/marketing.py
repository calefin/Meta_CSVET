from pydantic import BaseModel
from typing import Any, Dict, List, Optional
from datetime import datetime


class EmailTemplateCreate(BaseModel):
    name: str
    subject: str
    html_body: str


class EmailTemplateOut(BaseModel):
    id: str
    name: str
    subject: str
    html_body: str
    created_by: str
    created_at: datetime
    updated_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class SegmentFilters(BaseModel):
    roles: Optional[List[str]] = None
    plan_names: Optional[List[str]] = None
    locales: Optional[List[str]] = None
    signup_after: Optional[datetime] = None
    signup_before: Optional[datetime] = None
    species_ids: Optional[List[str]] = None


class CampaignCreate(BaseModel):
    name: str
    template_id: Optional[str] = None
    subject: str
    html_body: str
    from_address: str
    segment_filters: Optional[SegmentFilters] = None


class CampaignOut(BaseModel):
    id: str
    name: str
    template_id: Optional[str] = None
    subject: str
    html_body: str
    from_address: str
    segment_filters_json: Optional[Dict[str, Any]] = None
    status: str
    total_recipients: int
    total_sent: int
    total_failed: int
    created_by: str
    created_at: datetime
    sent_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class SegmentPreviewOut(BaseModel):
    recipient_count: int
    exceeds_daily_limit: bool
    daily_limit: int
