import { ENV } from "./env";

interface SendEmailOptions {
  to: string;
  cc?: string | string[];
  subject: string;
  html: string;
  text?: string;
}

async function getTransporter() {
  try {
    const nodemailer = await import("nodemailer");
    return nodemailer.createTransport({
      host: process.env.SMTP_HOST || "smtp.gmail.com",
      port: parseInt(process.env.SMTP_PORT || "587"),
      secure: process.env.SMTP_SECURE === "true",
      auth: {
        user: process.env.SMTP_USER,
        pass: process.env.SMTP_PASS,
      },
    });
  } catch {
    return null;
  }
}

export async function sendEmail(opts: SendEmailOptions): Promise<boolean> {
  if (!process.env.SMTP_USER || !process.env.SMTP_PASS) {
    console.warn("[Email] SMTP not configured — email not sent");
    return false;
  }

  try {
    const transporter = await getTransporter();
    if (!transporter) return false;

    await transporter.sendMail({
      from: process.env.SMTP_FROM || `Prof-Aid <${process.env.SMTP_USER}>`,
      to: opts.to,
      ...(opts.cc && { cc: opts.cc }),
      subject: opts.subject,
      html: opts.html,
      text: opts.text,
    });

    console.info(`[Email] Sent to ${opts.to} (CC: ${opts.cc || ""}): ${opts.subject}`);
    return true;
  } catch (error) {
    console.error("[Email] Send failed:", error);
    return false;
  }
}

export function quizResponseEmailHtml(data: {
  examName: string;
  studentName: string;
  studentEmail?: string;
  score?: string;
  submittedAt: Date;
}): string {
  return `
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
      <h2 style="color: #059669;">Nova resposta recebida — Prof-Aid</h2>
      <p>Uma nova resposta foi enviada para <strong>${data.examName}</strong>.</p>
      <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Aluno</strong></td><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">${data.studentName}</td></tr>
        ${data.studentEmail ? `<tr><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>E-mail</strong></td><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">${data.studentEmail}</td></tr>` : ""}
        ${data.score ? `<tr><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Pontuação</strong></td><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">${data.score}</td></tr>` : ""}
        <tr><td style="padding: 8px;"><strong>Enviado em</strong></td><td style="padding: 8px;">${data.submittedAt.toLocaleString("pt-BR")}</td></tr>
      </table>
      <p style="color: #6b7280; font-size: 12px;">Acesse o Prof-Aid para ver todas as respostas.</p>
    </div>
  `;
}

export async function sendSupportTicketNotification(ticket: { id: number; title: string; description: string; priority: string }) {
  const subject = `[Prof-Aid Suporte] Novo Chamado #${ticket.id}: ${ticket.title}`;
  const html = `
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
      <h2 style="color: #4f46e5; border-bottom: 2px solid #4f46e5; padding-bottom: 8px;">Novo Chamado de Suporte</h2>
      <p>Um novo chamado de suporte foi aberto na plataforma Prof-Aid.</p>
      <div style="background-color: #f9fafb; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #4f46e5;">
        <p><strong>Chamado:</strong> #${ticket.id}</p>
        <p><strong>Assunto:</strong> ${ticket.title}</p>
        <p><strong>Prioridade:</strong> ${ticket.priority}</p>
        <p><strong>Descrição:</strong></p>
        <p style="white-space: pre-wrap; font-style: italic;">${ticket.description}</p>
      </div>
      <p style="font-size: 12px; color: #6b7280;">Este e-mail foi enviado automaticamente. Por favor, gerencie o chamado no painel do administrador.</p>
    </div>
  `;
  await sendEmail({
    to: "help@prof-aid.com",
    cc: ["calefi@csvet.com.br", "ascalefi@gmail.com"],
    subject,
    html,
  });
}

export async function sendProjectQuoteNotification(quote: { id: number; name: string; email: string; phone: string; company: string; role: string; projectType: string; estimatedVolume: string; timeframe: string; description: string; features?: string[] }) {
  const subject = `[Prof-Aid Comercial] Nova Solicitação de Orçamento #${quote.id} - ${quote.company}`;
  
  const mappedType = quote.projectType === "questions" ? "Banco de Questões" :
                     quote.projectType === "exams" ? "Sistema de Provas" :
                     quote.projectType === "grading" ? "Correção Óptica" : "Outro";
                     
  const mappedVolume = quote.estimatedVolume === "v1" ? "Pequeno" :
                       quote.estimatedVolume === "v2" ? "Médio" : "Grande";
                       
  const mappedTimeframe = quote.timeframe === "now" ? "Imediato" :
                          quote.timeframe === "month" ? "30 dias" :
                          quote.timeframe === "quarter" ? "90 dias" : "Futuro";

  const html = `
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
      <h2 style="color: #059669; border-bottom: 2px solid #059669; padding-bottom: 8px;">Nova Solicitação de Orçamento</h2>
      <p>Uma nova solicitação de orçamento de projeto corporativo foi recebida.</p>
      
      <h3 style="color: #374151; margin-top: 20px;">Dados do Contato</h3>
      <table style="width: 100%; border-collapse: collapse;">
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6; width: 35%;"><strong>Nome:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${quote.name}</td></tr>
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><strong>E-mail:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><a href="mailto:${quote.email}">${quote.email}</a></td></tr>
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><strong>Telefone:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${quote.phone}</td></tr>
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><strong>Empresa/Instituição:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${quote.company}</td></tr>
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><strong>Cargo:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${quote.role}</td></tr>
      </table>

      <h3 style="color: #374151; margin-top: 20px;">Detalhes do Projeto</h3>
      <table style="width: 100%; border-collapse: collapse;">
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6; width: 35%;"><strong>Tipo de Serviço:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${mappedType}</td></tr>
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><strong>Volume Estimado:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${mappedVolume}</td></tr>
        <tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><strong>Prazo de Início:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${mappedTimeframe}</td></tr>
        ${quote.features && quote.features.length > 0 ? `<tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;"><strong>Recursos Desejados:</strong></td><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">${quote.features.join(", ")}</td></tr>` : ""}
      </table>

      <h3 style="color: #374151; margin-top: 20px;">Descrição dos Requisitos:</h3>
      <p style="background-color: #f9fafb; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-style: italic; white-space: pre-wrap;">${quote.description}</p>
      
      <p style="font-size: 12px; color: #6b7280; margin-top: 20px;">Este e-mail foi enviado automaticamente. Por favor, gerencie e responda à proposta comercial no painel do administrador.</p>
    </div>
  `;
  await sendEmail({
    to: "help@prof-aid.com",
    cc: ["calefi@csvet.com.br", "ascalefi@gmail.com"],
    subject,
    html,
  });
}
