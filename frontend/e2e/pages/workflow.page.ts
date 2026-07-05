import {expect, Locator, Page} from '@playwright/test';

export class WorkflowPage {
    public constructor(private readonly page: Page) {}

    public async expectVisible(): Promise<void> {
        await expect(this.page.locator('.status-card')).toBeVisible();
    }

    public statusRow(name: string): Locator {
        return this.page.locator('.status-row').filter({has: this.page.locator(`.status-name`).and(this.page.locator(`input[value="${name}"]`))});
    }

    public rowByIndex(index: number): Locator {
        return this.page.locator('.status-row').nth(index);
    }

    public async statusCount(): Promise<number> {
        return this.page.locator('.status-row').count();
    }

    public async addStatus(name: string): Promise<void> {
        const initial = await this.statusCount();
        this.page.once('dialog', async (dialog) => {
            await dialog.accept(name);
        });
        await this.page.getByRole('button', {name: '+ Add status'}).click();
        await expect(this.page.locator('.status-row')).toHaveCount(initial + 1, {timeout: 10_000});
    }

    public async renameStatusAt(index: number, newName: string): Promise<void> {
        const input = this.rowByIndex(index).locator('input.status-name');
        await input.fill(newName);
        await input.blur();
        // The PUT happens on blur — wait until the value is committed.
        await expect(input).toHaveValue(newName);
    }

    public async changeColorAt(index: number, color: string): Promise<void> {
        const input = this.rowByIndex(index).locator('input.status-color');
        await input.evaluate((el, value) => {
            const colorInput = el as HTMLInputElement;
            colorInput.value = value;
            colorInput.dispatchEvent(new Event('input', {bubbles: true}));
            colorInput.dispatchEvent(new Event('change', {bubbles: true}));
        }, color);
        await expect(input).toHaveValue(color);
    }

    public async deleteStatusAt(index: number): Promise<void> {
        const initial = await this.statusCount();
        this.page.once('dialog', (dialog) => dialog.accept());
        await this.rowByIndex(index).locator('.status-delete').click();
        await expect(this.page.locator('.status-row')).toHaveCount(initial - 1, {timeout: 10_000});
    }

    public async statusNameAt(index: number): Promise<string> {
        return this.rowByIndex(index).locator('input.status-name').inputValue();
    }
}
