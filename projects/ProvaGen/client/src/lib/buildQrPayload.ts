interface BuildQrPayloadOpts {
  templateId?: string;
  userId?: string;
  templateVersion?: string;
  classId?: string;
}

export function buildQrPayload(opts: BuildQrPayloadOpts): string | undefined {
  const { templateId, userId, templateVersion, classId } = opts;
  if (!templateId && !userId && !templateVersion && !classId) return undefined;
  // Format: TemplateID|UserID|TemplateVersion|ClassID
  return [templateId ?? '', userId ?? '', templateVersion ?? '', classId ?? ''].join("|");
}

export default buildQrPayload;
