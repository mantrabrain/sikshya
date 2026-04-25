export type EmailTemplateApi = {
  id: string;
  name: string;
  description: string;
  event: string;
  category: string;
  /** Legacy hint (system templates: student / admin / instructor / custom). */
  recipient: string;
  /** Where to send: merge tags like {{student_email}}, {{instructor_email}}, {{admin_email}}, or a literal address. */
  recipient_to: string;
  template_type: 'system' | 'custom';
  enabled: boolean;
  subject: string;
  body_html: string;
  body_preview: string;
  merge_tags: string[];
  /** When true, the add-on / plan gate is not met — edit, preview, and bulk actions are blocked server-side. */
  locked?: boolean;
  locked_reason?: string;
  required_addon?: string;
  required_feature?: string;
  required_addon_label?: string;
  required_plan_label?: string;
};
