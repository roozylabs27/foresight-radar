import { Form, Input, Select, Radio } from "antd";
import React, { useEffect, useImperativeHandle, forwardRef } from "react";

const FormStatusAction = forwardRef(
    ({ initialValues, errors, onFinish, loading, status_actions }, ref) => {
        const [form] = Form.useForm();
        useEffect(() => {
            form.setFieldsValue(initialValues);
        }, [initialValues, form]);

        // Expose form submit function to parent component
        useImperativeHandle(ref, () => ({
            submit: () => {
                form.submit();
            },
            reset: () => {
                form.setFieldsValue(initialValues);
            },
        }));
        return (
            <Form
                form={form}
                name="basic"
                disabled={loading}
                initialValues={initialValues}
                layout="vertical"
                onFinish={onFinish}
            >
                <Form.Item
                    name="keyword"
                    label="Keyword"
                    validateStatus={errors?.keyword ? "error" : ""}
                    help={errors?.keyword}
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please input the keyword!",
                        },
                    ]}
                >
                    <Input placeholder="Enter keyword" disabled={true} />
                </Form.Item>
                <Form.Item
                    name="description"
                    label="Description"
                    validateStatus={errors?.description ? "error" : ""}
                    help={errors?.description}
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please input the description!",
                        },
                    ]}
                >
                    <Input placeholder="Enter description" disabled={true} />
                </Form.Item>
                <Form.Item
                    name="dimension"
                    label="Dimension"
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please select the dimension!",
                        },
                    ]}
                >
                    <Select
                        style={{ width: "100%" }}
                        placeholder="Select a dimension"
                        disabled={true}
                    />
                </Form.Item>
                <Form.Item
                    name="status_action_id"
                    label="Status Action"
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please select the status action !",
                        },
                    ]}
                >
                    <Select
                        style={{ width: "100%" }}
                        placeholder="Select a status action"
                        options={status_actions}
                    />
                </Form.Item>
            </Form>
        );
    }
);

export default FormStatusAction;
