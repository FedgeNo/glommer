/**
 * The client twin of Entity.php - one topic in a list of them,
 * rebuilt from the payload as /topics/{type}/ pages in more of itself.
 *
 * The address and the ban control's name arrive already worked out rather than
 * being rebuilt here: which words a kind's address uses is the server's table,
 * and a second copy of it in the browser is a second thing to keep right.
 */
export class Entity {
    static fromData(data) {
        const chip = new Entity();
        Object.assign(chip, data);

        return chip;
    }

    toElement() {
        const chip = document.createElement('div');
        chip.className = 'Entity';

        const link = document.createElement('a');
        link.className = 'TrendingEntityLink';
        link.href = this.url;
        link.textContent = this.title;

        if (this.count !== null && this.count !== undefined) {
            const count = document.createElement('span');
            count.className = 'TrendingEntityCount';
            count.textContent = String(this.count);
            link.appendChild(count);
        }

        chip.appendChild(link);

        if (this.canModerate) {
            const ban = document.createElement('button');
            ban.type = 'button';
            ban.className = 'Button TrendingEntityBanButton';
            ban.dataset.entityType = this.type;
            ban.dataset.entityValue = this.title;
            ban.textContent = this.banLabel;
            chip.appendChild(ban);
        }

        return chip;
    }
}
