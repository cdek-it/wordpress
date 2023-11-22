import { useCallback, useEffect, useState } from '@wordpress/element';
import { debounce } from 'lodash';
import { __ } from '@wordpress/i18n';
import { CURRENCY } from '@woocommerce/settings';

import '../styles/delivery-price.scss';

export const DeliveryPrice = ({ input }) => {
    const [doorRules, setDoorRules] = useState([]);
    const [officeRules, setOfficeRules] = useState([]);

    const debouncedSetRules = useCallback(debounce((doorRules, officeRules) => {
        input.val(JSON.stringify({
            door: doorRules, office: officeRules,
        }));
    }, 300), []);

    useEffect(() => {
        debouncedSetRules(doorRules, officeRules);
    }, [doorRules, officeRules]);

    useEffect(() => {
        try {
            const rules = JSON.parse(input.val());
            setDoorRules(rules.door);
            setOfficeRules(rules.office);
        } catch (e) {
            setDoorRules([{
                to: null, type: 'percentage', value: 100,
            }]);
            setOfficeRules([]);
        }
    }, []);

    const addDoorRule = () => {
        doorRules[doorRules.length - 1].to = (doorRules[doorRules.length - 2] || { to: 0 }).to + 1;
        setDoorRules([...doorRules, {
            to: null, type: 'percentage', value: 100,
        }]);
    };

    const changeDoorTo = (e, index) => {
        doorRules[index].to = parseInt(e.target.value);
        doorRules.forEach((e, i) => {
            if (i === 0 || e.to === null) {
                return;
            }
            if (e.to <= doorRules[i - 1].to) {
                doorRules[i].to = doorRules[i - 1].to + 1;
            }
        });
        setDoorRules([...doorRules]);
    };
    const changeDoorType = (e, index) => {
        doorRules[index].type = e.target.value;
        setDoorRules([...doorRules]);
    };

    const changeDoorValue = (e, index) => {
        doorRules[index].value = e.target.value;
        setDoorRules([...doorRules]);
    };

    const removeDoorRule = (index) => {
        if (index === (doorRules.length - 1)) {
            doorRules[index - 1].to = null;
        }
        setDoorRules(doorRules.filter((e, i) => (i + 1) !== index));
    };

    return (<>
        <div className="cdek-delivery-rules">
            <div><h4>{__('Правила для доставки курьером', 'cdek-official')}</h4>
                <span className="button"
                      onClick={addDoorRule}>+</span>
            </div>
            {doorRules.map((rule, index) => (<div
                key={index}>{__('Сумма заказа', 'cdek-official')} {doorRules[index - 1] && <>{__('от', 'cdek-official')} {doorRules[index - 1].to + 1}{CURRENCY.symbol}</>} {rule.to && <>{__('до', 'cdek-official')}
                <input defaultValue={rule.to}
                       min={doorRules[index - 1] ? doorRules[index - 1].to + 1 : 0} type="number"
                       onInput={(e) => changeDoorTo(e, index)} />{CURRENCY.symbol}</>} {doorRules.length === 1 && <>{__('любая', 'cdek-official')}</>}, {__('стоимость доставки', 'cdek-official')}
                <select onChange={(e) => changeDoorType(e, index)}
                        className="cdek-selector" defaultValue={rule.type}>
                    <option value="free">
                        {__('бесплатно', 'cdek-official')}
                    </option>
                    <option value="percentage">
                        {__('взять процентом', 'cdek-official')}
                    </option>
                    <option value="fixed">
                        {__('фиксировать на', 'cdek-official')}
                    </option>
                    <option value="amount">
                        {__('изменить на', 'cdek-official')}
                    </option>
                </select>
                {rule.type !== 'free' && <input defaultValue={rule.value} type="number" min="0"
                                                onInput={(e) => changeDoorValue(e, index)} />}
                {rule.type === 'percentage' && <>%</>}
                {(rule.type === 'amount' || rule.type === 'fixed') && <>{CURRENCY.symbol}</>}
                {index !== 0 &&
                    <span className="button button-link-delete" onClick={() => removeDoorRule(index)}>-</span>}
            </div>))}
        </div>
    </>);
};
