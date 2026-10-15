import type { Page } from '@playwright/test';
import { openView } from '../../../packages/test-kit/e2e/helpers';

/** Page object for the Single view. Selectors use roles and landmarks, never theme-specific classes. */
export class SinglePage {
  constructor(readonly page: Page) {}

  async open(path = '/', status = 200): Promise<void> {
    await openView(this.page, path, status);
  }

  get main() {
    return this.page.getByRole('main');
  }

  get headings() {
    return this.page.getByRole('heading');
  }
}
