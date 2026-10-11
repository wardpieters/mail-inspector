import { mount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import FindingCard from './FindingCard.vue';
it('renders hostile SMTP and DNS evidence as text', () => {
 const payload='<img src=x onerror=alert(1)><script>alert(2)</script>';
 const wrapper=mount(FindingCard,{props:{finding:{key:'answer',scope:'domain',outcome:'fail',severity:'high',title:payload,explanation:payload,recommendation:null,evidence:{reply:payload},duration_ms:0}}});
 expect(wrapper.find('img').exists()).toBe(false); expect(wrapper.find('script').exists()).toBe(false);expect(wrapper.text()).toContain(payload);
});
