import { EyeInvisibleOutlined, EyeTwoTone } from "@ant-design/icons";
import { Form, Input, Select } from "antd";
import React, { useEffect, useImperativeHandle, forwardRef } from "react";

const FormUser = forwardRef(
    ({ initialValues, isEditMode, roles, errors, onFinish, loading }, ref) => {
        const [form] = Form.useForm();
        
        useEffect(() => {
            if (isEditMode) {
                form.setFieldsValue(initialValues);
            } else {
                form.resetFields();
            }
        }, [isEditMode, initialValues, form]);

        // Expose form submit function to parent component
        useImperativeHandle(ref, () => ({
            submit: () => {
                form.submit();
            },
            reset: () => {
                form.setFieldsValue(initialValues);
            },
        }));

        // const validateKeywordLength = (_, value) => {
        //     if (!value || value.length >= 5) {
        //         return Promise.resolve();
        //     }
        //     return Promise.reject(
        //         new Error("Keyword must be at least 5 characters long")
        //     );
        // };

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
                    name="name"
                    label="Name"
                    validateStatus={errors?.name ? "error" : ""}
                    help={errors?.name}
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please input the name!",
                        },
                    ]}
                >
                    <Input
                        placeholder="Enter name"
                        autoFocus={isEditMode ? false : true}
                    />
                </Form.Item>
                <Form.Item
                    name="email"
                    label="Email"
                    validateTrigger="onBlur"
                    validateStatus={errors?.email ? "error" : ""}
                    help={errors?.email}
                    rules={[
                        {
                            type: "email",
                            message: "Email is not valid!",
                        },
                        {
                            required: true,
                            message: "Please input the email!",
                        },
                    ]}
                >
                    <Input placeholder="Enter an email" />
                </Form.Item>
                <Form.Item
                    name="role_id"
                    label="Role"
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please select the role!",
                        },
                    ]}
                >
                    <Select
                        style={{ width: "100%" }}
                        placeholder="Select a roles"
                        options={roles}
                    />
                </Form.Item>
                {!isEditMode && (
                    <>
                        <Form.Item
                            name="password"
                            label="Password"
                            rules={[
                                {
                                    required: true,
                                    message: "Please input the password!",
                                },
                                {
                                    min: 6,
                                    message: "Password minimal 6 characters !",
                                },
                            ]}
                        >
                            <Input.Password
                                placeholder="Enter password"
                                iconRender={(visible) =>
                                    visible ? (
                                        <EyeTwoTone />
                                    ) : (
                                        <EyeInvisibleOutlined />
                                    )
                                }
                            />
                        </Form.Item>
                    </>
                )}
            </Form>
        );
    }
);

export default FormUser;
