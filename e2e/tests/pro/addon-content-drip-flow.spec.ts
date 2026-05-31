import { test, expect } from '@playwright/test';
import { createCourseViaRest, createLessonViaRest, getAdminNonce } from '../../utils/factories';

test.use({ storageState: 'e2e/.auth/admin.json' });

test.beforeAll(async ({ request }) => {
  const res = await request.get('/wp-json/sikshya/v1/');
  const body = await res.json().catch(() => ({}));
  const routes: string[] = Object.keys(body?.routes ?? {});
  test.skip(
    !routes.some((r) => /\/pro\/drip-rules/.test(r)),
    'content_drip not active or addon disabled',
  );
});

test.describe('addon: content_drip rule CRUD', () => {
  test('admin can create a drip rule, list it, and delete it', async ({ page, request }) => {
    const nonce = await getAdminNonce(page);

    // Ensure addon is enabled (idempotent).
    await request.post('/wp-json/sikshya/v1/admin/addons/content_drip/enable', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    });

    const course = await createCourseViaRest(page, request, {
      title: `E2E drip course ${Date.now()}`,
      type: 'free',
    });
    const lesson = await createLessonViaRest(page, request, course.id);

    // Create a drip rule: lesson unlocks 0 days after enrollment.
    const save = await request.post('/wp-json/sikshya/v1/pro/drip-rules', {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
      data: {
        course_id: course.id,
        lesson_id: lesson.id,
        rule_type: 'delay_days',
        rule_value: '0',
      },
    });
    const saveBody = await save.json().catch(() => ({}));
    expect(save.status(), JSON.stringify(saveBody).slice(0, 200)).toBeLessThan(400);

    // List rules for the course — our new rule should appear.
    const list = await request.get(
      `/wp-json/sikshya/v1/pro/drip-rules?course_id=${course.id}`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    const listBody = await list.json().catch(() => ({}));
    expect(list.ok(), JSON.stringify(listBody).slice(0, 200)).toBeTruthy();
    const rules: { id?: number; lesson_id?: number; course_id?: number }[] =
      listBody?.rules ?? listBody?.data?.rules ?? listBody?.items ?? listBody?.data ?? [];
    const ours = rules.find(
      (r) => Number(r.lesson_id ?? 0) === lesson.id && Number(r.course_id ?? 0) === course.id,
    );
    expect(ours, `our rule should be in list of ${rules.length} items`).toBeDefined();

    if (ours?.id) {
      const del = await request.delete(`/wp-json/sikshya/v1/pro/drip-rules/${ours.id}`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      expect(del.status()).toBeLessThan(400);
    }
  });
});
